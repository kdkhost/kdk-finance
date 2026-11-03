{{--
 ============================================================================
 SISTEMA FINANCEIRO MULTIMOEDAS & GESTÃO DE INFRAESTRUTURA
 ============================================================================
 Desenvolvedor : George Marcelo de Oliveira Pimentel
 Empresa       : KDKHost Soluções (CNPJ: 33.986.975/0001-90)
 Contato       : contato@kdkhost.com.br | https://kdkhost.com.br
 Função        : Responsável Técnico / Engenheiro de Software Full-Stack
 ============================================================================
--}}
@extends('admin.layouts.app')

@section('title', 'Nova fatura')
@section('eyebrow', 'Faturas')
@section('subtitle', 'Cadastre uma fatura internacional ou uma licença e o sistema aplica câmbio, spread e tributos.')

@section('content')
    @include('invoices._form', [
        'action' => route('invoices.store'),
        'method' => 'POST',
    ])
@endsection
