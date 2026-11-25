{{-- Static policy pages: about, contact, privacy, terms, shipping, returns. FR-CAT-11 --}}
@extends('storefront.layouts.store')
@section('title', $sf->field($static, 'title').' · '.$sf->store->name)
@section('content')
    <section class="mv-section mv-w-narrow">
        <div class="mv-container">
            <h1 class="mv-h2">{{ $sf->field($static, 'title') }}</h1>
            <div class="mv-rte">{!! $sf->rich($sf->field($static, 'body')) !!}</div>
        </div>
    </section>
@endsection
