<?php

/** Envía un correo de forma segura: si el SMTP falla, no interrumpe la operación */
function enviarCorreoSeguro(?string $destino, string $asunto, string $mensaje): void
{
    if (!$destino) {
        return;
    }

    try {
        \Illuminate\Support\Facades\Mail::raw($mensaje, function ($mail) use ($destino, $asunto) {
            $mail->to($destino)->subject($asunto);
        });
    } catch (\Throwable $e) {
        \Illuminate\Support\Facades\Log::warning('No se pudo enviar correo: '.$e->getMessage());
    }
}
