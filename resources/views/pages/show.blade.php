@extends('layouts.base')

{{-- Foundation body: correct <head> for every published page, with a minimal
     shell. The rich per-page-type bodies (discount guides, ranks, bases, …) are
     layered on this layout one page-family per follow-up PR. --}}
@section('content')
    <main>
        <h1>{{ $page->title }}</h1>
        @include('partials.trust.editorial-policy')
    </main>
@endsection
