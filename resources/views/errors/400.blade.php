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
@extends('errors.layout')

@section('title', 'Endereço não permitido')
@section('code', '400')
@section('heading', 'Endereço não permitido')
@section('message', 'O domínio usado para acessar o painel não está na lista de hosts autorizados. Confira o endereço ou ajuste a lista em Configurações → Segurança.')
