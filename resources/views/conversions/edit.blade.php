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

@section('title', 'Editar conversão')
@section('eyebrow', 'Conversão #'.$conversion->id)
@section('subtitle', 'Ajuste o valor, a cotação ou as tarifas. O total é recalculado no servidor ao salvar.')

@section('content')
    @include('conversions._form', [
        'action' => route('conversions.update', $conversion),
        'method' => 'PUT',
    ])
@endsection
