{{--
    Reusable help hint: a small "?" next to a label that opens a popover with the
    explanation, so long helper text does not clutter the form.

    Usage:
      @include('partials.help', ['text' => 'Full explanation...'])
      @include('partials.help', ['text' => '...', 'title' => 'Optional heading'])
--}}
<a role="button" tabindex="0" class="help-dot"
   data-toggle="popover" data-trigger="focus" data-placement="{{ $placement ?? 'top' }}"
   @isset($title) data-title="{{ $title }}" @endisset
   data-content="{{ $text }}"
   aria-label="{{ __('Help') }}"><i class="fas fa-question"></i></a>
