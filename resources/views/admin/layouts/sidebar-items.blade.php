{{--
 ============================================================================
 SISTEMA FINANCEIRO MULTIMOEDAS & GESTÃO DE INFRAESTRUTURA
 ============================================================================
 Desenvolvedor : Marcelo Brad - RJ
 Empresa       : KDKHost Soluções (CNPJ: 33.986.975/0001-90)
 Contato       : contato@kdkhost.com.br | https://kdkhost.com.br
 Função        : Responsável Técnico / Engenheiro de Software Full-Stack
 ============================================================================

 Árvore de navegação do painel: até três níveis, com o ramo da página atual
 sempre aberto. Os itens chegam prontos do sidebar.blade.php.
--}}
@php
    $level = $level ?? 1;
@endphp
@foreach ($items as $item)
    @php
        $hasChildren = ! empty($item['children']);
        $isActive = ! empty($item['active']);
        $isActivePath = ! empty($item['active_path']);
        $isOpen = ! empty($item['open']) || ! empty($item['open_default']);
        $menuId = 'sidebar-menu-'.$item['key'];
    @endphp

    @if (! $hasChildren && empty($item['url']))
        @continue
    @endif

    @if ($hasChildren)
        <li class="nav-item has-treeview {{ $isOpen ? 'is-open' : '' }}" data-tree-item="{{ $item['key'] }}" @if ($isActivePath) data-active-path="true" @endif>
            <button type="button"
                    class="nav-link sidebar-treeview-toggle {{ $isActivePath ? 'is-current' : '' }}"
                    data-treeview-toggle
                    data-default-open="{{ ! empty($item['open_default']) ? 'true' : 'false' }}"
                    aria-expanded="{{ $isOpen ? 'true' : 'false' }}"
                    aria-controls="{{ $menuId }}">
                <i class="bi {{ $item['icon'] }}" aria-hidden="true"></i>
                <span>{{ $item['label'] }}</span>
                <i class="bi bi-chevron-right treeview-caret" aria-hidden="true"></i>
            </button>

            <div class="sidebar-treeview" id="{{ $menuId }}">
                <ul class="nav flex-column sidebar-treeview-list sidebar-level-{{ $level + 1 }}">
                    @include('admin.layouts.sidebar-items', ['items' => $item['children'], 'level' => $level + 1])
                </ul>
            </div>
        </li>
    @else
        <li class="nav-item">
            <a href="{{ $item['url'] }}"
               class="nav-link {{ $isActive ? 'active' : '' }}"
               @if ($isActive) aria-current="page" @endif>
                <i class="bi {{ $item['icon'] }}" aria-hidden="true"></i>
                <span>{{ $item['label'] }}</span>
                @if (! empty($item['badge']))
                    <span class="badge" data-live="unread-count" @if ($unread === 0) hidden @endif>{{ $unread > 9 ? '9+' : $unread }}</span>
                @endif
            </a>
        </li>
    @endif
@endforeach
