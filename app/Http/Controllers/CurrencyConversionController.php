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

use App\Models\CurrencyConversion;
use App\Services\CurrencyConversionService;
use App\Support\RichText;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CurrencyConversionController extends Controller
{
    public function __construct(private readonly CurrencyConversionService $conversions) {}

    public function index(Request $request): View
    {
        $conversions = $this->filtered($request)->paginate(12)->withQueryString();
        $avgCost = CurrencyConversion::query()->whereNotNull('statement_cost_percent')->avg('statement_cost_percent');

        return view('conversions.index', [
            'conversions' => $conversions,
            'filters' => $request->only(['q', 'pair', 'calibrated', 'sort', 'dir']),
            'summary' => [
                'total' => CurrencyConversion::query()->count(),
                'calibrated' => CurrencyConversion::query()->whereNotNull('statement_amount')->count(),
                'avg_cost' => $avgCost !== null ? round((float) $avgCost, 3) : null,
                'total_brl' => (float) CurrencyConversion::query()->sum('total_brl'),
            ],
            'quote' => $this->conversions->quote('EUR', 'USD'),
            'defaults' => $this->conversions->defaults(),
        ]);
    }

    public function create(): View
    {
        return view('conversions.create', [
            'conversion' => new CurrencyConversion([
                'from_currency' => 'EUR',
                'to_currency' => 'USD',
                'amount_from' => 0,
            ]),
            'quote' => $this->conversions->quote('EUR', 'USD'),
            'defaults' => $this->conversions->defaults(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        $conversion = CurrencyConversion::query()->create(array_merge(
            $this->conversions->build($data),
            ['created_by' => $request->user()->id]
        ));

        return redirect()
            ->route('conversions.show', $conversion)
            ->with('success', 'Conversão salva: '.$conversion->toSymbol().' '.number_format((float) $conversion->total_to, 2, ',', '.').' é o valor necessário.');
    }

    public function show(CurrencyConversion $conversion): View
    {
        return view('conversions.show', [
            'conversion' => $conversion->load('creator'),
            'quote' => $this->conversions->quote($conversion->from_currency, $conversion->to_currency),
        ]);
    }

    public function edit(CurrencyConversion $conversion): View
    {
        return view('conversions.edit', [
            'conversion' => $conversion,
            'quote' => $this->conversions->quote($conversion->from_currency, $conversion->to_currency),
            'defaults' => $this->conversions->defaults(),
        ]);
    }

    public function update(Request $request, CurrencyConversion $conversion): RedirectResponse
    {
        $data = $this->validated($request);

        $conversion->update($this->conversions->build($data));

        return redirect()
            ->route('conversions.show', $conversion)
            ->with('success', 'Conversão atualizada e recalculada.');
    }

    public function destroy(CurrencyConversion $conversion): RedirectResponse
    {
        $conversion->delete();

        return redirect()
            ->route('conversions.index')
            ->with('success', 'Conversão excluída.');
    }

    public function recalculate(CurrencyConversion $conversion): RedirectResponse
    {
        $previous = (float) $conversion->total_to;
        $result = $this->conversions->recalculate($conversion, true);

        $conversion->update($result);

        return back()->with(
            'success',
            'Recalculado com a cotação atual ('.number_format((float) $result['market_rate'], 6, ',', '.').'): '
            .$conversion->toSymbol().' '.number_format((float) $result['total_to'], 2, ',', '.').'.'
            .($previous > 0 ? ' Antes: '.$conversion->toSymbol().' '.number_format($previous, 2, ',', '.').'.' : '')
        );
    }

    /**
     * Confere o valor realmente cobrado na fatura do cartão/conta internacional.
     */
    public function calibrate(Request $request, CurrencyConversion $conversion): RedirectResponse
    {
        $data = $request->validate([
            'statement_amount' => ['required', 'numeric', 'min:0', 'max:999999999.99'],
        ], [], ['statement_amount' => 'valor cobrado na fatura']);

        $statement = round((float) $data['statement_amount'], 2);
        $base = (float) $conversion->base_to;

        $conversion->update([
            'statement_amount' => $statement,
            'statement_cost_percent' => $base > 0 ? round((($statement / $base) - 1) * 100, 3) : null,
            'statement_checked_at' => now(),
        ]);

        $difference = round($statement - (float) $conversion->total_to, 2);

        return back()->with(
            'success',
            'Fatura conferida: '.$conversion->toSymbol().' '.number_format($statement, 2, ',', '.').'. '
            .'Diferença de '.$conversion->toSymbol().' '.number_format($difference, 2, ',', '.').' em relação ao cálculo.'
        );
    }

    /**
     * Cotação do par para a prévia ao vivo do formulário (JSON).
     */
    public function quote(Request $request): JsonResponse
    {
        $data = $request->validate([
            'from' => ['required', Rule::in(array_keys(CurrencyConversion::currencies()))],
            'to' => ['required', Rule::in(array_keys(CurrencyConversion::currencies())), 'different:from'],
            'fresh' => ['nullable', 'boolean'],
        ]);

        return response()->json($this->conversions->quote($data['from'], $data['to'], (bool) ($data['fresh'] ?? false)));
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'description' => ['required', 'string', 'max:150'],
            'from_currency' => ['required', Rule::in(array_keys(CurrencyConversion::currencies()))],
            'to_currency' => ['required', Rule::in(array_keys(CurrencyConversion::currencies())), 'different:from_currency'],
            'amount_from' => ['required', 'numeric', 'min:0.01', 'max:999999999.99'],
            'market_rate' => ['nullable', 'numeric', 'min:0.000001', 'max:9999'],
            'provider_rate' => ['nullable', 'numeric', 'min:0.000001', 'max:9999'],
            'spread_percent' => ['nullable', 'numeric', 'min:-100', 'max:100'],
            'iof_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'fixed_fee' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'buffer_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'notes' => ['nullable', 'string', 'max:3000'],
        ], [], [
            'description' => 'descrição',
            'from_currency' => 'moeda de origem',
            'to_currency' => 'moeda de destino',
            'amount_from' => 'valor de origem',
            'market_rate' => 'cotação',
            'provider_rate' => 'cotação do provedor',
            'spread_percent' => 'spread',
            'iof_percent' => 'IOF',
            'fixed_fee' => 'taxa fixa',
            'buffer_percent' => 'margem de segurança',
            'notes' => 'observações',
        ]);

        $data['notes'] = RichText::sanitize($data['notes'] ?? null);

        return $data;
    }

    private function filtered(Request $request): Builder
    {
        $query = CurrencyConversion::query();

        if (in_array($request->query('pair'), CurrencyConversion::pairs(), true)) {
            [$from, $to] = explode('-', (string) $request->query('pair'));
            $query->where('from_currency', $from)->where('to_currency', $to);
        }

        if ($request->boolean('calibrated')) {
            $query->whereNotNull('statement_amount');
        }

        if ($term = trim((string) $request->query('q'))) {
            $query->where(function (Builder $builder) use ($term): void {
                $builder->where('description', 'like', '%'.$term.'%')
                    ->orWhere('notes', 'like', '%'.$term.'%');
            });
        }

        $sort = (string) $request->query('sort', 'created_at');
        $direction = strtolower((string) $request->query('dir', 'desc')) === 'asc' ? 'asc' : 'desc';

        if (! in_array($sort, ['created_at', 'amount_from', 'total_to', 'total_brl', 'effective_rate'], true)) {
            $sort = 'created_at';
        }

        return $query->orderBy($sort, $direction)->orderBy('id', 'desc');
    }
}
