@section('title', 'Edge Agents')
@extends('layouts.home')

@section('content')
    <schema-editor-container :initial-data='@json($initialData)'></edge-agent-container>
@endsection
