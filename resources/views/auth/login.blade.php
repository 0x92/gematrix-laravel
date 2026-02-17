@php
    $meta = [
        'title' => 'Login - Gematrix',
        'robots' => 'noindex,nofollow',
        'canonical' => url('/login'),
    ];
@endphp
<x-layouts.app :title="'Login'" :meta="$meta">
    <section class="auth-shell reveal">
        <article class="card auth-info-card">
            <p class="kicker"><i class="fa-solid fa-shield"></i> Secure Access</p>
            <h1>Welcome back</h1>
            <p class="subtitle">Sign in to manage favorites, workspace phrases, and admin analytics.</p>

            <div class="auth-highlights">
                <div><i class="fa-solid fa-bolt"></i> Fast phrase lookup and match explorer</div>
                <div><i class="fa-solid fa-chart-line"></i> Search and click analytics tracking</div>
                <div><i class="fa-solid fa-key"></i> Role-based admin access</div>
            </div>

            <div class="auth-tip">
                <strong><i class="fa-solid fa-circle-info"></i> Admin default</strong>
                <span><code>admin@gematrix.local</code></span>
            </div>
        </article>

        <article class="card auth-form-card">
            <h2>{{ __('messages.login') }}</h2>

            @if ($errors->any())
                <div class="alert alert-error text-sm mb-3">
                    <i class="fa-solid fa-triangle-exclamation"></i>
                    <span>{{ $errors->first() }}</span>
                </div>
            @endif

            <form method="POST" class="stack-sm" action="/login">
                @csrf

                <label class="form-control w-full">
                    <div class="label"><span class="label-text">Email</span></div>
                    <input class="input input-bordered w-full" name="email" type="email" placeholder="name@example.com" required value="{{ old('email') }}" autocomplete="email" autofocus>
                </label>

                <label class="form-control w-full">
                    <div class="label"><span class="label-text">Password</span></div>
                    <input class="input input-bordered w-full" name="password" type="password" placeholder="••••••••" required autocomplete="current-password">
                </label>

                <label class="label cursor-pointer justify-start gap-2">
                    <input class="checkbox checkbox-sm" type="checkbox" name="remember">
                    <span class="label-text">Remember me</span>
                </label>

                <button class="btn btn-primary w-full" type="submit">
                    <i class="fa-solid fa-right-to-bracket"></i>
                    {{ __('messages.login') }}
                </button>

                <p class="auth-switch">No account yet? <a href="/register">Create one now</a></p>
            </form>
        </article>
    </section>
</x-layouts.app>
