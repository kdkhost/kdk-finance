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

@section('title', 'Editar fatura')
@section('eyebrow', 'Faturas')
@section('subtitle', $invoice->description)

@section('content')
    @include('invoices._form', [
        'action' => route('invoices.update', $invoice),
        'method' => 'PUT',
    ])
@endsection
