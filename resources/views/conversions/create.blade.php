{{--
 ============================================================================
 SISTEMA FINANCEIRO MULTIMOEDAS & GESTÃO DE INFRAESTRUTURA
 ============================================================================
 Desenvolvedor : Marcelo Brad - RJ
 Empresa       : KDKHost Soluções (CNPJ: 33.986.975/0001-90)
 Contato       : contato@kdkhost.com.br | https://kdkhost.com.br
 Função        : Responsável Técnico / Engenheiro de Software Full-Stack
 ============================================================================
--}}
@extends('admin.layouts.app')

@section('title', 'Nova conversão')
@section('eyebrow', 'Conversões')
@section('subtitle', 'Converta entre euro, dólar e real para saber quanto reservar — já com spread, IOF e tarifas do cartão.')

@section('content')
    @include('conversions._form', [
        'action' => route('conversions.store'),
        'method' => 'POST',
    ])
@endsection
