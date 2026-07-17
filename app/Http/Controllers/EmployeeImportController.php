<?php

namespace App\Http\Controllers;

use App\Models\Area;
use App\Models\AuditLog;
use App\Models\Employee;
use App\Models\Position;
use App\Models\Schedule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class EmployeeImportController extends Controller
{
    /** Rows prepared with dropdowns/formats in the template */
    private const TEMPLATE_ROWS = 500;

    /** Column layout of the template (fixed positions; headers are localized) */
    private function columns(): array
    {
        return [
            'A' => __('Document number').' *',
            'B' => __('First names').' *',
            'C' => __('Last names').' *',
            'D' => __('Schedule').' *',
            'E' => __('Area'),
            'F' => __('Position'),
            'G' => __('Hire date').' (YYYY-MM-DD)',
        ];
    }

    /** Downloads the .xlsx template with dropdowns and cell formats */
    public function template()
    {
        $schedules = Schedule::where('is_active', true)->orderBy('name')->pluck('name');
        $areas = Area::where('is_active', true)->orderBy('name')->pluck('name');
        $positions = Position::where('is_active', true)->orderBy('name')->pluck('name');

        $spreadsheet = new Spreadsheet();

        // ---- Sheet 1: data entry ----
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle(__('Employees'));

        foreach ($this->columns() as $col => $label) {
            $sheet->setCellValue($col.'1', $label);
        }
        $sheet->getStyle('A1:G1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '0F1B2D']],
            'borders' => ['bottom' => ['borderStyle' => Border::BORDER_THIN]],
        ]);
        foreach (['A' => 18, 'B' => 24, 'C' => 24, 'D' => 22, 'E' => 22, 'F' => 22, 'G' => 22] as $col => $width) {
            $sheet->getColumnDimension($col)->setWidth($width);
        }
        $sheet->freezePane('A2');

        $last = self::TEMPLATE_ROWS + 1;

        // Document number as TEXT so leading zeros are kept
        $sheet->getStyle("A2:A{$last}")->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);
        // Hire date as text date to avoid locale-dependent serials
        $sheet->getStyle("G2:G{$last}")->getNumberFormat()->setFormatCode('yyyy-mm-dd');

        // ---- Sheet 2: lists that feed the dropdowns ----
        $lists = $spreadsheet->createSheet();
        $lists->setTitle('Lists');
        foreach ($schedules->values() as $i => $name) {
            $lists->setCellValue('A'.($i + 1), $name);
        }
        foreach ($areas->values() as $i => $name) {
            $lists->setCellValue('B'.($i + 1), $name);
        }
        foreach ($positions->values() as $i => $name) {
            $lists->setCellValue('C'.($i + 1), $name);
        }
        $lists->setSheetState(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet::SHEETSTATE_HIDDEN);

        // ---- Dropdowns ----
        $addDropdown = function (string $col, string $listColumn, int $count, bool $strict, string $error) use ($sheet, $last) {
            if ($count === 0) {
                return;
            }
            for ($row = 2; $row <= $last; $row++) {
                $validation = $sheet->getCell($col.$row)->getDataValidation();
                $validation->setType(DataValidation::TYPE_LIST)
                    ->setErrorStyle($strict ? DataValidation::STYLE_STOP : DataValidation::STYLE_WARNING)
                    ->setAllowBlank(true)
                    ->setShowDropDown(true)
                    ->setShowErrorMessage(true)
                    ->setErrorTitle(__('Invalid value'))
                    ->setError($error)
                    ->setFormula1('Lists!$'.$listColumn.'$1:$'.$listColumn.'$'.$count);
            }
        };

        $addDropdown('D', 'A', $schedules->count(), true, __('Pick a schedule from the list (create it first in Schedules if missing).'));
        $addDropdown('E', 'B', $areas->count(), false, __('Not in the list: it will be created automatically on import.'));
        $addDropdown('F', 'C', $positions->count(), false, __('Not in the list: it will be created automatically on import.'));

        // ---- Sheet 3: instructions ----
        $help = $spreadsheet->createSheet();
        $help->setTitle(__('Instructions'));
        $rows = [
            __('EMPLOYEE IMPORT TEMPLATE'),
            '',
            __('• Columns marked with * are required.'),
            __('• Document number: 8 to 12 digits, unique per employee.'),
            __('• Schedule: pick one from the dropdown (they come from the Schedules module).'),
            __('• Area and Position: pick from the dropdown or type a new name (it will be created).'),
            __('• Hire date: optional, format YYYY-MM-DD (e.g. 2026-03-15).'),
            __('• Do not change the column order or the header row.'),
            __('• Nothing is imported if the file has errors: the system lists them per row so you can fix and re-upload.'),
        ];
        foreach ($rows as $i => $text) {
            $help->setCellValue('A'.($i + 1), $text);
        }
        $help->getStyle('A1')->getFont()->setBold(true);
        $help->getColumnDimension('A')->setWidth(95);

        $spreadsheet->setActiveSheetIndex(0);

        $file = tempnam(sys_get_temp_dir(), 'employees_template');
        (new Xlsx($spreadsheet))->save($file);

        return response()->download($file, 'employees_import_template.xlsx')->deleteFileAfterSend(true);
    }

    /** Imports the uploaded file. All-or-nothing: with any error, no row is saved. */
    public function store(Request $request)
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv,txt', 'max:4096'],
        ]);

        try {
            $spreadsheet = IOFactory::load($request->file('file')->getRealPath());
        } catch (\Throwable $e) {
            return back()->with('error', __('The file could not be read. Upload the .xlsx template or a CSV.'));
        }

        $sheet = $spreadsheet->getSheet(0);
        $highestRow = $sheet->getHighestDataRow();

        $schedules = Schedule::where('is_active', true)->get()->keyBy(fn ($s) => mb_strtolower(trim($s->name)));
        $existingDocuments = Employee::pluck('document_number')->flip();

        $errors = [];
        $rows = [];
        $seenDocuments = [];

        for ($row = 2; $row <= $highestRow; $row++) {
            $document = trim((string) $sheet->getCell("A{$row}")->getValue());
            $firstName = trim((string) $sheet->getCell("B{$row}")->getValue());
            $lastName = trim((string) $sheet->getCell("C{$row}")->getValue());
            $scheduleName = trim((string) $sheet->getCell("D{$row}")->getValue());
            $areaName = trim((string) $sheet->getCell("E{$row}")->getValue());
            $positionName = trim((string) $sheet->getCell("F{$row}")->getValue());
            $hireDateRaw = $sheet->getCell("G{$row}")->getValue();

            // Fully empty row: ignore
            if ($document === '' && $firstName === '' && $lastName === '' && $scheduleName === '') {
                continue;
            }

            $rowErrors = [];

            // Document number: 8-12 digits, unique in DB and within the file
            if (!preg_match('/^\d{8,12}$/', $document)) {
                $rowErrors[] = __('the document number must have 8 to 12 digits');
            } elseif (isset($existingDocuments[$document])) {
                $rowErrors[] = __('an employee with that document number already exists');
            } elseif (isset($seenDocuments[$document])) {
                $rowErrors[] = __('the document number is repeated in the file (row :row)', ['row' => $seenDocuments[$document]]);
            }

            if ($firstName === '' || mb_strlen($firstName) > 100) {
                $rowErrors[] = __('first names are required (max. 100 characters)');
            }
            if ($lastName === '' || mb_strlen($lastName) > 100) {
                $rowErrors[] = __('last names are required (max. 100 characters)');
            }

            $schedule = $schedules->get(mb_strtolower($scheduleName));
            if ($scheduleName === '' || !$schedule) {
                $rowErrors[] = __("the schedule ':name' does not exist (create it first in Schedules)", ['name' => $scheduleName]);
            }

            // Hire date: optional; accepts YYYY-MM-DD text or a real Excel date
            $hireDate = null;
            if ($hireDateRaw !== null && $hireDateRaw !== '') {
                if (is_numeric($hireDateRaw)) {
                    try {
                        $hireDate = ExcelDate::excelToDateTimeObject((float) $hireDateRaw)->format('Y-m-d');
                    } catch (\Throwable) {
                        $rowErrors[] = __('the hire date is not valid (use YYYY-MM-DD)');
                    }
                } else {
                    try {
                        $hireDate = \Carbon\Carbon::createFromFormat('Y-m-d', trim((string) $hireDateRaw))->format('Y-m-d');
                    } catch (\Throwable) {
                        $rowErrors[] = __('the hire date is not valid (use YYYY-MM-DD)');
                    }
                }
            }

            if ($rowErrors) {
                $errors[] = ['row' => $row, 'messages' => $rowErrors];
                continue;
            }

            $seenDocuments[$document] = $row;
            $rows[] = [
                'document_number' => $document,
                'first_name' => $firstName,
                'last_name' => $lastName,
                'schedule_id' => $schedule->id,
                'area' => $areaName,
                'position' => $positionName,
                'hire_date' => $hireDate,
            ];
        }

        if (!$rows && !$errors) {
            return back()->with('error', __('The file is empty: no data rows were found.'));
        }

        if ($errors) {
            return back()
                ->with('import_errors', $errors)
                ->with('error', __('Nothing was imported: fix the :count row(s) with errors and upload the file again.', ['count' => count($errors)]));
        }

        DB::transaction(function () use ($rows) {
            // Areas and positions are created on the fly by name
            $areaIds = [];
            $positionIds = [];

            foreach ($rows as $data) {
                $areaId = null;
                if ($data['area'] !== '') {
                    $key = mb_strtolower($data['area']);
                    $areaIds[$key] ??= Area::firstOrCreate(['name' => $data['area']])->id;
                    $areaId = $areaIds[$key];
                }

                $positionId = null;
                if ($data['position'] !== '') {
                    $key = mb_strtolower($data['position']);
                    $positionIds[$key] ??= Position::firstOrCreate(['name' => $data['position']])->id;
                    $positionId = $positionIds[$key];
                }

                Employee::create([
                    'document_number' => $data['document_number'],
                    'first_name' => $data['first_name'],
                    'last_name' => $data['last_name'],
                    'schedule_id' => $data['schedule_id'],
                    'area_id' => $areaId,
                    'position_id' => $positionId,
                    'hire_date' => $data['hire_date'],
                ]);
            }
        });

        AuditLog::record('CREATE', 'Employees', __(':count employee(s) imported from a file', ['count' => count($rows)]));

        return redirect()->route('employees.index')
            ->with('ok', __(':count employee(s) imported successfully. You can now enroll their faces.', ['count' => count($rows)]));
    }
}
