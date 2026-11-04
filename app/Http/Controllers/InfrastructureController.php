<?php

/**
 * ============================================================================
 * SISTEMA FINANCEIRO MULTIMOEDAS & GESTÃO DE INFRAESTRUTURA
 * ============================================================================
 * Desenvolvedor : Marcelo Brad - RJ
 * Empresa       : KDKHost Soluções (CNPJ: 33.986.975/0001-90)
 * Contato       : contato@kdkhost.com.br | https://kdkhost.com.br
 * Função        : Responsável Técnico / Engenheiro de Software Full-Stack
 * ============================================================================
 */

namespace App\Http\Controllers;

use App\Models\OvhIp;
use App\Models\OvhServiceItem;
use App\Services\ExchangeRateService;
use App\Services\OvhInventoryService;
use Illuminate\View\View;

class InfrastructureController extends Controller
{
    public function __invoke(ExchangeRateService $exchange, OvhInventoryService $inventory): View
    {
        $rates = $exchange->rates();
        $eurRate = (float) ($rates['EUR']['rate'] ?? 0);

        return view('infrastructure.index', [
            'rates' => $rates,
            'eurRate' => $eurRate,
            'services' => $inventory->pricedServices($eurRate),
            'ips' => OvhIp::query()->orderBy('version')->orderBy('block')->get(),
            'totals' => $inventory->totals($eurRate),
            'lastSync' => OvhServiceItem::query()->max('updated_at'),
        ]);
    }
}
