<x-app-layout name="Similaridade Visual">
    <div class="container py-5">
        @if ($grupos->isEmpty())
            <div class="alert alert-info">
                <i class="bi bi-info-circle me-2"></i>
                Nenhuma similaridade visual encontrada ainda. Rode o comando artisan media:phash.
            </div>
        @endif
        @foreach ($grupos as $rootId => $arquivos)
            @php
                $root = $arquivos->first();
            @endphp
            <div class="card mb-5 shadow-sm border-warning">
                <div class="card-header bg-warning bg-opacity-10 d-flex justify-content-between align-items-center">
                    <strong>Hash Visual:</strong>
                    <code class="text-dark">
                        {{ substr($root->file_hash, 0, 16) }}...
                    </code>
                    <span class="badge bg-warning text-dark">{{ $arquivos->count() }} fotos parecidas</span>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        @foreach ($arquivos as $arquivo)
                            <div class="col-md-3 col-sm-6">
                                <div class="card h-100 shadow-sm"> {{-- Adicionei a classe card aqui para estrutura --}}
                                    {{-- 1. REMOVIDO height: 180px DAQUI --}}
                                    <div class="card-img-top bg-dark"
                                        style="min-height: 200px; position: relative; display: flex; align-items: center; justify-content: center;">
                                        @php
                                            $extensao = strtolower(pathinfo($arquivo->file_path, PATHINFO_EXTENSION));
                                            $isVideo = in_array($extensao, ['mp4', 'mov', 'avi', 'wmv']);
                                        @endphp

                                        @if ($isVideo)
                                            <video
                                                src="{{ route('media.stream', ['path' => $arquivo->file_path]) }}#t=0.1"
                                                class="w-100" style="max-height: 400px; object-fit: contain;" controls
                                                muted preload="metadata">
                                            </video>

                                            <div style="position: absolute; top: 10px; right: 10px; z-index: 10;">
                                                <span class="badge bg-dark opacity-75"><i class="bi bi-play-fill"></i>
                                                    MP4</span>
                                            </div>
                                        @else
                                            <img src="{{ route('media.stream', ['path' => $arquivo->file_path]) }}"
                                                class="w-100" style="height: 200px; object-fit: contain;"
                                                loading="lazy">
                                        @endif
                                    </div>

                                    <div class="card-body p-2">
                                        <p class="card-text small mb-1 text-truncate"
                                            title="{{ $arquivo->photo_name }}">
                                            {{ $arquivo->photo_name }}
                                        </p>
                                        <p class="mb-2"
                                            style="font-size: 0.7rem; font-weight: {{ is_null($arquivo->similarity_score) ? 'bold' : 'normal' }}; color: {{ is_null($arquivo->similarity_score) ? '#179a29' : '#000000' }} !important;">
                                            Distância: {{ $arquivo->similarity_score ?? 'Original' }}
                                        </p>
                                        <button
                                            class="btn {{ is_null($arquivo->similarity_score) ? 'btn-secondary' : 'btn-warning' }} btn-sm w-100 mb-1"
                                            onclick="naoSimilar({{ $arquivo->id }}, this)"
                                            {{ is_null($arquivo->similarity_score) ? 'disabled' : '' }}>

                                            <i class="bi bi-check-circle"></i>
                                            {{ is_null($arquivo->similarity_score) ? 'Arquivo Original' : 'Não Similar - Manter' }}
                                        </button>

                                        <button class="btn btn-danger btn-sm w-100"
                                            onclick="confirmDelete({{ $arquivo->id }}, this)">
                                            <i class="bi bi-trash"></i> Excluir
                                        </button>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        @endforeach

        {{-- Renderiza os links de paginação do Laravel --}}
        <div class="d-flex justify-content-center mt-4">
            {{ $paginator->links() }}
        </div>

    </div>

    @push('scripts')
        <script>
            function confirmDelete(id, btn) {
                if (confirm('Deseja apagar este arquivo permanentemente?')) {
                    btn.disabled = true;
                    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';

                    // Corrigido para bater com o nome da rota no PHP
                    let rotaTemplate = '{{ route('duplicates.similar.delete', ':id') }}';
                    let url = rotaTemplate.replace(':id', id);
                    fetch(url, {
                            method: "DELETE",
                            headers: {
                                "X-CSRF-TOKEN": "{{ csrf_token() }}",
                                "Content-Type": "application/json"
                            }
                        })
                        .then(res => res.json())
                        .then(data => {
                            if (data.success) {
                                // Remove o card da interface sem precisar dar reload na página toda
                                btn.closest('.col-md-3').remove();

                                // O reload só é necessário se você quiser reorganizar a paginação imediatamente
                                // window.location.reload(); 
                            } else {
                                alert('Erro: ' + data.message);
                                btn.disabled = false;
                                btn.innerHTML = '<i class="bi bi-trash"></i> Excluir';
                            }
                        })
                        .catch(error => {
                            console.error('Erro:', error);
                            btn.disabled = false;
                            btn.innerHTML = 'Excluir';
                        });
                }
            }

            function naoSimilar(id, btn) {
                if (confirm('Deseja mover este arquivo para a Library e desvincular a similaridade?')) {
                    btn.disabled = true;
                    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';

                    fetch(`/duplicates/manter`, {
                            method: "POST",
                            headers: {
                                "X-CSRF-TOKEN": "{{ csrf_token() }}",
                                "Content-Type": "application/json"
                            },
                            body: JSON.stringify({
                                id: id
                            }) // Enviamos o ID, que é infalível
                        })
                        .then(res => res.json())
                        .then(data => {
                            if (data.success) {
                                btn.closest('.col-md-3').remove();
                            } else {
                                alert('Erro: ' + data.message);
                                btn.disabled = false;
                                btn.innerHTML = 'Não Similar - Manter';
                            }
                        });
                }
            }
        </script>
    @endpush
</x-app-layout>
