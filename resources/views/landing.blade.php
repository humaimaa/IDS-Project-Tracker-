@extends('layouts.public')
@section('content')
<main>
    <section class="landing-hero" aria-labelledby="hero-heading">
        <header class="landing-header">
            <a href="{{ route('home') }}" class="landing-brand" aria-label="IDS Project Tracker home"><img src="{{ asset('images/ids-logo.png') }}" alt="Government of Khyber Pakhtunkhwa" width="66" height="70"><span>International Development Section<small>Planning & Development Department · Khyber Pakhtunkhwa</small></span></a>
            <nav aria-label="Main navigation"><a href="#purpose">About</a><a href="#partners">Partners</a></nav>
        </header>
        <div class="landing-hero-content"><div class="landing-glass"><h1 id="hero-heading">Every project. Every milestone. One place.</h1><a class="landing-button" href="{{ route('login') }}">Get Started <span aria-hidden="true">↗</span></a></div></div>
    </section>
    <section id="purpose" class="landing-section landing-purpose" aria-labelledby="purpose-heading">
        <div><p class="landing-eyebrow">PURPOSE OF THE TRACKER</p><h2 id="purpose-heading">A clearer view of<br>development progress.</h2></div>
        <div><p class="landing-intro">The IDS Project Tracker brings project information, activity progress and team discussions together—helping teams follow development initiatives across Khyber Pakhtunkhwa.</p><p class="landing-muted">From the first concept to implementation, see what is moving forward, identify delays and keep everyone working from the same information.</p></div>
    </section>
    <section class="landing-features" aria-labelledby="features-heading"><div class="landing-section">
        <p class="landing-eyebrow">INSIDE THE TRACKER</p><h2 id="features-heading">Everything you need to stay on track.</h2>
        <div class="landing-feature-grid">
            @foreach ([['01', 'Project overview', 'Explore project costs, implementing agencies, key dates and related documents in one place.'], ['02', 'Stage-by-stage progress', 'Follow activities from concept through PC-I development to implementation, with clear progress and status.'], ['03', 'Conversations that connect', 'Discuss projects and activities, reply to comments and keep track of new updates with project chat.']] as [$number, $title, $description])
            <article class="landing-feature"><span class="landing-feature-number">{{ $number }} <span aria-hidden="true">↗</span></span><h3>{{ $title }}</h3><p>{{ $description }}</p></article>
            @endforeach
        </div>
    </div></section>
    <section id="partners" class="landing-partners" aria-labelledby="partners-heading">
        <p class="landing-eyebrow">WORKING TOGETHER</p><h2 id="partners-heading">Our development partners</h2><p class="landing-muted">Shared commitment. Lasting progress.</p>
        @php
            $partners = ['adb.png' => 'Asian Development Bank', 'ausaid.jpg' => 'Australian Aid', 'cvf.png' => 'CVF', 'eu.png' => 'European Union', 'dfid.jpg' => 'UK Department for International Development', 'sfd.jpg' => 'Saudi Fund for Development', 'eucom.png' => 'European Commission', 'ida.png' => 'International Development Association', 'undp.png' => 'United Nations Development Programme', 'inl.png' => 'INL', 'usaid.png' => 'USAID', 'jica.png' => 'Japan International Cooperation Agency', 'kfw.png' => 'KfW', 'wfp.png' => 'World Food Programme', 'worldbank.png' => 'World Bank', 'unicef.png' => 'UNICEF', 'unops.png' => 'UNOPS'];
        @endphp
        <div class="partner-carousel" tabindex="0" role="region" aria-label="Development partner logos. Hover or focus to pause scrolling."><div class="partner-track">
            @for ($copy = 0; $copy < 2; $copy++)<div class="partner-group" @if($copy) aria-hidden="true" @endif>
                @foreach ($partners as $file => $name)<div class="partner-logo"><img src="{{ asset('images/partner-'.$file) }}" alt="{{ $copy ? '' : $name }}" width="150" height="80" loading="lazy"></div>@endforeach
            </div>@endfor
        </div></div>
    </section>
</main>
<footer class="landing-footer"><span>© {{ date('Y') }} IDS Project Tracker</span><span>Planning & Development Department, Khyber Pakhtunkhwa</span><a href="{{ route('login') }}">Get Started ↗</a></footer>
@endsection
