@extends('install.layout')

@section('content')
    <span class="eyebrow rise">{{ __('install.eyebrow') }}</span>
    <h1 class="rise" style="animation-delay: 60ms">{{ __('install.done.title') }}</h1>
    <p class="lead rise" style="animation-delay: 120ms">{{ __('install.done.body', ['email' => $email]) }}</p>

    <section class="shell rise" style="animation-delay: 180ms">
        <div class="core">
            <a class="cta" href="{{ $adminUrl }}" style="margin-top: 0">
                <span class="label">{{ __('install.done.cta') }}</span>
                <span class="ic" aria-hidden="true"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 6l6 6-6 6"/></svg></span>
            </a>

            @unless ($linked)
                <div class="alert bad" role="status">{{ __('install.done.storage') }}</div>
            @endunless

            <hr class="divider">
            <h2 class="step">{{ __('install.done.next') }}</h2>
            <ol class="next">
                @foreach (__('install.done.next_items') as $item)
                    <li>{{ $item }}</li>
                @endforeach
            </ol>
        </div>
    </section>
@endsection
