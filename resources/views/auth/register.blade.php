@php
    $meta = [
        'title' => 'Register - Gematrix',
        'robots' => 'noindex,nofollow',
        'canonical' => url('/register'),
    ];
@endphp
<x-layouts.app :title="'Register'" :meta="$meta">
    <section class="auth-shell reveal">
        <article class="card auth-info-card">
            <p class="kicker"><i class="fa-solid fa-wand-magic-sparkles"></i> Join Gematrix</p>
            <h1>Create your account</h1>
            <p class="subtitle">Save phrases, customize your ciphers, and track your own gematria workflow.</p>

            <div class="auth-highlights">
                <div><i class="fa-solid fa-star"></i> Personal favorites and saved phrases</div>
                <div><i class="fa-solid fa-sliders"></i> Cipher preferences per account</div>
                <div><i class="fa-solid fa-compass"></i> Faster research via explorer tools</div>
            </div>
        </article>

        <article class="card auth-form-card">
            <h2>{{ __('messages.register') }}</h2>

            @if ($errors->any())
                <div class="alert alert-error text-sm mb-3">
                    <i class="fa-solid fa-triangle-exclamation"></i>
                    <span>{{ $errors->first() }}</span>
                </div>
            @endif

            <form method="POST" class="stack-sm" action="/register">
                @csrf

                <label class="form-control w-full">
                    <div class="label"><span class="label-text">Name</span></div>
                    <input class="input input-bordered w-full" name="name" placeholder="Your name" required value="{{ old('name') }}" autocomplete="name" autofocus>
                </label>

                <label class="form-control w-full">
                    <div class="label"><span class="label-text">Email</span></div>
                    <input class="input input-bordered w-full" name="email" type="email" placeholder="name@example.com" required value="{{ old('email') }}" autocomplete="email">
                </label>

                <label class="form-control w-full">
                    <div class="label"><span class="label-text">Password</span></div>
                    <input class="input input-bordered w-full" name="password" type="password" placeholder="Min. 8 characters" required autocomplete="new-password">
                </label>

                <label class="form-control w-full">
                    <div class="label"><span class="label-text">Password confirmation</span></div>
                    <input class="input input-bordered w-full" name="password_confirmation" type="password" placeholder="Repeat password" required autocomplete="new-password">
                </label>

                <button class="btn btn-primary w-full" type="submit">
                    <i class="fa-solid fa-user-plus"></i>
                    {{ __('messages.register') }}
                </button>

                <p class="auth-switch">Already registered? <a href="/login">Go to login</a></p>
            </form>
        </article>
    </section>
</x-layouts.app>
