<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>FotoFlow - Fotos e Vídeos</title>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.9.2/dist/umd/popper.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.min.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link rel="stylesheet" href="{{ asset('css/style.css') }}">

    <style>
        .topnav a {
            color: #ccc;
            padding: 8px 12px;
            text-decoration: none;
            transition: 0.3s;
            display: flex;
            align-items: center;
            border-radius: 4px;
        }

        .topnav a:hover,
        .topnav a.active {
            color: #fff;
            background-color: #444;
        }

        .topnav a.active {
            border-bottom: 2px solid #04AA6D;
            border-radius: 4px 4px 0 0;
        }

        .service-status-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 5px;
        }

        /* Ajuste para o formulário de busca não ocupar muito espaço em telas menores */
        .search-container {
            flex-grow: 1;
            max-width: 300px;
            margin: 0 15px;
        }
    </style>
    @stack('styles')
</head>

<body class="bg-light">
    <div class="topnav"
        style="display: flex; align-items: center; padding: 5px 15px; background-color: #222; height: 50px;">

        <!-- Navegação Principal (Ícones) -->
        <a class="{{ request()->routeIs('dashboard') ? 'active' : '' }}" href="{{ route('dashboard') }}"
            title="Dashboard / Início">
            <i class="bi bi-house-door-fill"></i>
        </a>

        <a class="{{ request()->routeIs('media.index') ? 'active' : '' }}" href="{{ route('media.index') }}"
            title="Explorar Galeria">
            <i class="bi bi-grid-3x3-gap-fill"></i>
        </a>

        <div style="width: 1px; height: 20px; background: #444; margin: 0 5px;"></div>

        <a href="{{ route('duplicates.exact') }}" title="Duplicados Exatos (Hash)"
            class="{{ request()->routeIs('duplicates.exact') ? 'active' : '' }}">
            <i class="bi bi-layers-fill"></i>
        </a>
        <a href="{{ route('duplicates.similares') }}" title="Similares (Phash)"
            class="{{ request()->routeIs('duplicates.similares') ? 'active' : '' }}">
            <i class="bi bi-images"></i>
        </a>
        <a href="{{ route('duplicates.cenario') }}" title="Mesmo Cenário (Tolerância Alta)"
            class="{{ request()->routeIs('duplicates.cenario') ? 'active' : '' }}">
            <i class="bi bi-bounding-box-circles"></i>
        </a>
        <!-- Favoritos -->
        <a href="{{ route('media.favorites') }}" title="Favoritos"
            class="{{ request()->routeIs('media.favorites') ? 'active' : '' }}">
            <i class="bi bi-star-fill" style="color: #ffc107;"></i>
        </a>

        <!-- Filtros de Tipo e Ordenação -->
        <div
            style="display: flex; border-left: 1px solid #444; margin-left: 8px; padding-left: 8px; align-items: center; gap: 5px;">
            <a href="{{ url()->current() }}?type=all" class="{{ request('type', 'all') == 'all' ? 'active' : '' }}"
                title="Todos">
                <i class="bi bi-collection-play"></i>
            </a>
            <a href="{{ url()->current() }}?type=photos" class="{{ request('type') == 'photos' ? 'active' : '' }}"
                title="Fotos">
                <i class="bi bi-camera-fill"></i>
            </a>
            <a href="{{ url()->current() }}?type=videos" class="{{ request('type') == 'videos' ? 'active' : '' }}"
                title="Vídeos">
                <i class="bi bi-film"></i>
            </a>

            <a href="{{ url()->current() }}?type={{ request('type', 'all') }}&sort=photo_name"
                class="{{ request('sort') == 'photo_name' ? 'active' : '' }}" title="Ordenar por Nome (Delphi)">
                <i class="bi bi-sort-alpha-down"></i>
            </a>
        </div>
        <!-- Upload de Media -->
        <div
            style="display: flex; border-left: 1px solid #444; margin-left: 8px; padding-left: 8px; align-items: center; gap: 5px;">

            <a href="{{ route('media.upload.show') }}" title="Upload de Mídia" class="text-decoration-none"
                style="color: #ccc; transition: 0.3s; display: flex; align-items: center; border-radius: 4px;"> 
                <i class="bi bi-upload"></i>
            </a>
        </div>

        <div style="width: 1px; height: 20px; background: #444; margin: 0 5px;"></div>
        <!-- Barra de Busca Compacta -->
        <div class="search-container" style="max-width: 400px; flex-grow: 1;">
            <form action="{{ route('media.index') }}" method="GET" class="d-flex" id="searchForm">
                <input type="hidden" name="type" value="{{ request('type', 'all') }}">
                <input type="hidden" name="sort" value="{{ request('sort') }}">

                <div class="input-group input-group-sm">
                    <div class="position-relative d-flex align-items-center flex-grow-1">
                        <input type="text" name="search" id="searchInput" class="form-control"
                            placeholder="Buscar..." value="{{ request('search') }}"
                            style="background: #555; color: #fff; border: 1px solid #666; font-size: 0.8rem; padding-right: 30px; width: 100%;">

                        {{-- Botão X --}}
                        <a href="{{ route('media.index', request()->except('search')) }}" id="clearSearch"
                            class="position-absolute border-0 bg-transparent text-light {{ request('search') ? '' : 'd-none' }}"
                            style="right: 10px; text-decoration: none; cursor: pointer; z-index: 10; opacity: 0.7;"
                            title="Limpar busca">
                            <i class="bi bi-x-lg" style="font-size: 0.75rem;"></i>
                        </a>
                    </div>

                    <button class="btn btn-secondary" type="submit" style="border: 1px solid #666; background: #666;">
                        <i class="bi bi-search"></i>
                    </button>
                </div>
            </form>
        </div>

        <!-- Ações de Serviço e Sistema -->
        <div style="margin-left: auto; display: flex; align-items: center; gap: 10px;">

            <select class="form-select bg-dark text-light border-secondary" id="gallerySelect"
                onchange="changeGallery(this.value)" style="font-size: 0.75rem; min-width: 120px; cursor: pointer;">
                <option value="">Todas Galerias</option>
                @foreach ($galerias as $galeria)
                    {{-- Comparamos com a variável currentGaleria que vem da Session/URL --}}
                    <option value="{{ $galeria }}"
                        {{ isset($currentGaleria) && $currentGaleria == $galeria ? 'selected' : '' }}>
                        {{ $galeria }}
                    </option>
                @endforeach
            </select>

            <form action="{{ route('media.reset') }}" method="POST" class="m-0"
                onsubmit="return confirm('PERIGO: Isso limpará todos os dados do banco MYSQL (media_data_v4).\n\nOs arquivos físicos e o banco POSTGRES (media_data_v3) serão preservados.\n\nDeseja continuar com o RESET do MySQL?')">
                @csrf
                <button type="submit" class="btn btn-outline-danger btn-sm" title="Resetar Banco de Dados MySQL"
                    style="padding: 2px 8px;">
                    <i class="bi bi-trash3"></i>
                </button>
            </form>

            <form method="POST" action="{{ route('logout') }}" class="m-0">
                @csrf
                <button type="submit" class="btn btn-link text-white text-decoration-none p-0" title="Sair">
                    <i class="bi bi-box-arrow-right" style="font-size: 1.2rem;"></i>
                </button>
            </form>
        </div>
    </div>

    <div class="container-fluid py-3">
        {{ $slot }}
    </div>

    <script>
        function toggleService() {
            const btn = document.getElementById('btn-service');

            // Define o nome do serviço (corresponde ao {name} na rota)
            const serviceName = 'MediaHashService';

            // Define a ação baseada no status atual (corresponde ao {action} na rota)
            const action = btn.title.includes('running') ? 'stop' : 'start';

            // Feedback visual
            btn.disabled = true;
            const originalContent = btn.innerHTML;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';

            // A URL montada será: /services/control/MediaHashService/start (ou /stop)
            const url = `/services/control/${serviceName}/${action}`;

            console.log(`Iniciando requisição para: ${url}`);

            fetch(url, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json',
                        'Content-Type': 'application/json'
                    }
                })
                .then(async res => {
                    const data = await res.json();

                    // Se o status for 500, o erro detalhado vindo do Controller estará no 'data.message'
                    if (!res.ok) {
                        console.error('Erro retornado pelo servidor:', data);
                        throw new Error(data.message || `Erro ${res.status}: Falha na execução do comando.`);
                    }
                    return data;
                })
                .then(data => {
                    console.log('Sucesso:', data.message);
                    // Sucesso: Espera o Windows processar e recarrega
                    setTimeout(() => {
                        location.reload();
                    }, 1500);
                })
                .catch(err => {
                    console.error('Falha no Fetch:', err);
                    // Exibe o erro real (ex: "Access is denied" ou "Service not found")
                    alert('ERRO NO SERVIDOR: ' + err.message);
                    btn.disabled = false;
                    btn.innerHTML = originalContent;
                });
        }

        // Lógica para mostrar/esconder o botão "X" dinamicamente
        document.getElementById('searchInput').addEventListener('input', function() {
            const clearBtn = document.getElementById('clearSearch');
            if (this.value.length > 0) {
                clearBtn.classList.remove('d-none');
            } else {
                // Se o input ficar vazio e não houver busca ativa na URL, esconde o X
                if (!window.location.search.includes('search=')) {
                    clearBtn.classList.add('d-none');
                }
            }
        });

        // Opcional: Se o usuário der ESC dentro do input, limpa o texto
        document.getElementById('searchInput').addEventListener('keydown', function(e) {
            if (e.key === "Escape") {
                this.value = '';
                document.getElementById('clearSearch').click();
            }
        });

        // ######################################################################################
        // Função para trocar a galeria via dropdown
        // ######################################################################################
        function changeGallery(galeria) {
            // Obtém a URL atual
            let url = new URL(window.location.href);

            // Se selecionou uma galeria, adiciona o parâmetro, senão remove
            if (galeria) {
                url.searchParams.set('galeria', galeria);
            } else {
                url.searchParams.delete('galeria');
            }

            // Ao trocar de galeria, geralmente é bom resetar para a página 1
            url.searchParams.delete('page');

            // Redireciona
            window.location.href = url.toString();
        }
    </script>
    @stack('scripts')
</body>

</html>
