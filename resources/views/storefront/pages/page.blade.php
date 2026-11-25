{{-- Custom builder page at /pages/{slug}. FR-BLD-02 --}}
@extends('storefront.layouts.store')
@section('title', $sf->field($page, 'title').' · '.$sf->store->name)
@section('content')
    {!! $renderer->render($sections, $sf) !!}
@endsection
