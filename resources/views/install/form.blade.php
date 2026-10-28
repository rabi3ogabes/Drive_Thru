@extends('install.layout')

@php
    $field = function (string $name, string $type = 'text', array $attributes = []) use ($problems, $old): string {
        $value = $type === 'password' ? '' : (string) ($old[$name] ?? $attributes['value'] ?? '');
        unset($attributes['value']);
        $extra = collect($attributes)->map(fn ($v, $k) => $k.'="'.e((string) $v).'"')->implode(' ');
        $invalid = isset($problems[$name]) ? 'aria-invalid="true" aria-describedby="'.$name.'-error"' : '';

        return '<input id="'.$name.'" name="'.$name.'" type="'.$type.'" value="'.e($value).'" '.$invalid.' '.$extra.'>';
    };
    $error = fn (string $name): string => isset($problems[$name]) ? '<p class="err" id="'.$name.'-error">'.e($problems[$name]).'</p>' : '';
@endphp

@section('content')
    <span class="eyebrow rise">{{ __('install.eyebrow') }}</span>
    <h1 class="rise" style="animation-delay: 60ms">{{ __('install.title') }}</h1>
    <p class="lead rise" style="animation-delay: 120ms">{{ __('install.lead') }}</p>

    @isset($problems['install'])
        <div class="alert bad" role="alert">{{ $problems['install'] }}</div>
    @endisset

    <form method="post" action="{{ url('/install') }}?lang={{ app()->getLocale() }}" novalidate
          onsubmit="const b=this.querySelector('[type=submit]'); b.disabled=true; b.querySelector('.label').textContent=b.dataset.working; b.querySelector('.ic').innerHTML='<span class=&quot;spin&quot;></span>';">

        {{-- 1. Server --}}
        <section class="shell rise" style="animation-delay: 180ms">
            <div class="core">
                <h2 class="step"><span class="num">1</span>{{ __('install.steps.server') }}</h2>
                @if ($ready)
                    <div class="alert good">{{ __('install.server.ok') }}</div>
                    <details>
                        <summary>PHP {{ PHP_VERSION }}</summary>
                        <ul class="checks">
                            @foreach ($requirements as $check)
                                <li><span class="dot"></span><span class="ltr">{{ $check['label'] }}</span></li>
                            @endforeach
                        </ul>
                    </details>
                @else
                    <div class="alert bad" role="alert">{{ __('install.server.missing') }}</div>
                    <ul class="checks">
                        @foreach ($requirements as $check)
                            <li @class(['no' => ! $check['ok']])><span class="dot"></span><span class="ltr">{{ $check['label'] }}@if ($check['detail'] !== '' && ! $check['ok']) ({{ $check['detail'] }})@endif</span></li>
                        @endforeach
                    </ul>
                    <p class="hint">{{ __('install.server.php') }}<br>{{ __('install.server.ext') }}<br>{{ __('install.server.writable') }}</p>
                @endif
            </div>
        </section>

        {{-- 2. Setup key --}}
        <section class="shell rise" style="animation-delay: 240ms">
            <div class="core">
                <h2 class="step"><span class="num">2</span>{{ __('install.steps.key') }}</h2>
                <p class="hint">{{ __('install.key.body') }}</p>
                <code class="file">{{ $keyFile }}</code>
                <div class="grid">
                    <div>
                        <label for="key">{{ __('install.key.label') }}</label>
                        {!! $field('key', 'password', ['class' => 'ltr', 'autocomplete' => 'off', 'spellcheck' => 'false', 'required' => 'required']) !!}
                        {!! $error('key') !!}
                    </div>
                </div>
            </div>
        </section>

        {{-- 3. Database --}}
        <section class="shell rise" style="animation-delay: 300ms">
            <div class="core">
                <h2 class="step"><span class="num">3</span>{{ __('install.steps.database') }}</h2>
                <p class="hint">{{ __('install.database.body') }}</p>
                <div class="grid two">
                    <div class="span">
                        <label for="db_database">{{ __('install.attributes.db_database') }}</label>
                        {!! $field('db_database', 'text', ['class' => 'ltr', 'placeholder' => 'u123456789_drivethru', 'autocomplete' => 'off', 'spellcheck' => 'false', 'required' => 'required']) !!}
                        {!! $error('db_database') !!}
                    </div>
                    <div>
                        <label for="db_username">{{ __('install.attributes.db_username') }}</label>
                        {!! $field('db_username', 'text', ['class' => 'ltr', 'placeholder' => 'u123456789_drivethru', 'autocomplete' => 'off', 'spellcheck' => 'false', 'required' => 'required']) !!}
                        {!! $error('db_username') !!}
                    </div>
                    <div>
                        <label for="db_password">{{ __('install.attributes.db_password') }}</label>
                        {!! $field('db_password', 'password', ['class' => 'ltr', 'autocomplete' => 'new-password']) !!}
                        {!! $error('db_password') !!}
                    </div>
                    <div>
                        <label for="db_host">{{ __('install.attributes.db_host') }}</label>
                        {!! $field('db_host', 'text', ['class' => 'ltr', 'value' => 'localhost', 'autocomplete' => 'off', 'spellcheck' => 'false', 'required' => 'required']) !!}
                        <p class="field-hint">{{ __('install.database.host_hint') }}</p>
                        {!! $error('db_host') !!}
                    </div>
                    <div>
                        <label for="db_port">{{ __('install.attributes.db_port') }}</label>
                        {!! $field('db_port', 'text', ['class' => 'ltr', 'value' => '3306', 'inputmode' => 'numeric', 'required' => 'required']) !!}
                        {!! $error('db_port') !!}
                    </div>
                </div>
            </div>
        </section>

        {{-- 4. Super admin --}}
        <section class="shell rise" style="animation-delay: 360ms">
            <div class="core">
                <h2 class="step"><span class="num">4</span>{{ __('install.steps.admin') }}</h2>
                <p class="hint">{{ __('install.admin.body') }}</p>
                <div class="grid two">
                    <div>
                        <label for="name">{{ __('install.attributes.name') }}</label>
                        {!! $field('name', 'text', ['autocomplete' => 'name', 'required' => 'required']) !!}
                        {!! $error('name') !!}
                    </div>
                    <div>
                        <label for="email">{{ __('install.attributes.email') }}</label>
                        {!! $field('email', 'email', ['class' => 'ltr', 'autocomplete' => 'email', 'required' => 'required']) !!}
                        {!! $error('email') !!}
                    </div>
                    <div>
                        <label for="password">{{ __('install.attributes.password') }}</label>
                        {!! $field('password', 'password', ['class' => 'ltr', 'autocomplete' => 'new-password', 'required' => 'required']) !!}
                        <p class="field-hint">{{ __('install.admin.password_hint') }}</p>
                        {!! $error('password') !!}
                    </div>
                    <div>
                        <label for="password_confirmation">{{ __('install.attributes.password_confirmation') }}</label>
                        {!! $field('password_confirmation', 'password', ['class' => 'ltr', 'autocomplete' => 'new-password', 'required' => 'required']) !!}
                    </div>
                </div>

                <button type="submit" class="cta" data-working="{{ __('install.working') }}" @disabled(! $ready)>
                    <span class="label">{{ __('install.submit') }}</span>
                    <span class="ic" aria-hidden="true"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 6l6 6-6 6"/></svg></span>
                </button>
            </div>
        </section>
    </form>
@endsection
