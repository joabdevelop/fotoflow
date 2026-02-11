<x-app-layout name="Favoritos">
    <div class="content">
        <div class="container-fluid">
            @if ($arquivos->isEmpty())
                <div class="alert alert-info">Nenhum favorito encontrado.</div>
            @else
                {{-- PRIMEIRO LOOP: Percorre os Grupos (organizados por photo_name) --}}
                @foreach ($arquivos as $nomeDoGrupo => $grupoDeArquivos)
                    <div class="row g-3">
                        {{-- SEGUNDO LOOP: Percorre os Arquivos dentro deste grupo específico --}}
                        <div
                            class="card-header bg-warning bg-opacity-10 d-flex justify-content-between align-items-center mb-0">
                            <strong>{{ $nomeDoGrupo ?: 'Sem Nome' }} ({{ $grupoDeArquivos->count() }})</strong>
                        </div>
                        <div class="card-body row g-3 mb-3 mt-0">
                            {{-- TERCEIRO LOOP: Percorre cada arquivo individualmente --}}
                            @foreach ($grupoDeArquivos as $arquivo)
                                <div class="col-md-3 col-sm-6 card-container" id="media-{{ $arquivo->id }}">
                                    <div class="card h-100 shadow-sm border-light bg-black">

                                        {{-- CONTAINER DA IMAGEM/VIDEO --}}
                                        <div class="card-img-container bg-dark"
                                            style="min-height: 200px; position: relative; display: flex; align-items: center; justify-content: center;">

                                            @php
                                                $extensao = strtolower(
                                                    pathinfo($arquivo->file_path, PATHINFO_EXTENSION),
                                                );
                                                $isVideo = in_array($extensao, [
                                                    'mp4',
                                                    'mov',
                                                    'avi',
                                                    'wmv',
                                                    'flv',
                                                    'mkv',
                                                    'webm',
                                                ]);
                                                $nativelySupportedVideo = in_array($extensao, ['mp4', 'webm', 'mkv']);
                                            @endphp

                                    @if ($isVideo)
                                        @if ($extensao === 'flv')
                                            {{-- Caso específico para FLV que não roda no Browser --}}
                                            <div class="text-center p-3">
                                                <i class="bi bi-file-earmark-play-fill text-warning"
                                                    style="font-size: 3.5rem;"></i>
                                                <div class="text-white small mt-2">Formato FLV</div>
                                                <div class="badge bg-danger" style="font-size: 0.6rem;">Aberto via
                                                    Player Local</div>
                                            </div>
                                        @elseif ($nativelySupportedVideo)
                                            {{-- Clique no vídeo abre o Preview Rápido no Modal --}}
                                            <div onclick="openFastPreview('{{ route('media.stream', ['path' => $arquivo->file_path]) }}', '{{ $arquivo->photo_name }}', true)"
                                                style="cursor: pointer; width: 100%;"
                                                title="Clique para Preview Rápido">
                                                <video
                                                    src="{{ route('media.stream', ['path' => $arquivo->file_path]) }}#t=0.1"
                                                    class="w-100" style="max-height: 200px; object-fit: contain;" muted
                                                    preload="metadata">
                                                </video>
                                            </div>
                                        @else
                                            {{-- Outros vídeos (AVI, WMV) --}}
                                            <div class="text-center text-muted">
                                                <i class="bi bi-camera-video-off" style="font-size: 3rem;"></i>
                                                <p class="small text-white">Preview indisponível
                                                    ({{ strtoupper($extensao) }})</p>
                                            </div>
                                        @endif
                                    @else
                                        {{-- Clique na Imagem abre o Preview Rápido no Modal --}}
                                        <img src="{{ route('media.stream', ['path' => $arquivo->file_path]) }}"
                                            onclick="openFastPreview('{{ route('media.stream', ['path' => $arquivo->file_path]) }}', '{{ $arquivo->photo_name }}', false)"
                                            class="w-100" style="height: 200px; object-fit: contain; cursor: pointer;"
                                            loading="lazy" title="Clique para Preview Rápido">
                                    @endif

                                            {{-- SEUS BOTÕES SOBREPOSTOS (Mantenha o código que você já tem) --}}
                                            <div
                                                style="position: absolute;  top: 10px; right: 10px; z-index: 20; display: flex; flex-direction: column;gap: 15px;">
                                                {{-- Botão Visualizar (VLC/IrfanView) - Destaque maior se for FLV --}}
                                                <button
                                                    class="btn btn-light btn-sm shadow-sm d-flex align-items-center justify-content-center border"
                                                    onclick="openMediaLocal('{{ addslashes($arquivo->file_path) }}')"
                                                    title="Visualizar Arquivo Localmente"
                                                    style="width: 25px; height: 25px; border-radius: 5px; opacity: {{ $extensao === 'flv' ? '1' : '0.5' }}; transition: opacity 0.3s;">
                                                    <i class="bi bi-eye" style="font-size: 0.9rem;"></i>
                                                </button>

                                                {{-- Botão Favorito --}}
                                                <button class="btn btn-light btn-sm shadow-sm border"
                                                    onclick="toggleFavorite({{ $arquivo->id }})"
                                                    style="width: 25px; height: 25px; opacity: 1;">
                                                    <i class="bi bi-star-fill" style="color: #ffc107;"></i>
                                                </button>
                                            </div>
                                        </div>

                                        {{-- CARD BODY (DESCRIÇÃO E TAMANHO) --}}
                                        <div class="card-body p-1 px-2">
                                            <div class="d-flex align-items-center justify-content-between gap-2">
                                                <div class="flex-grow-1 text-truncate" style="min-width: 0;">
                                                    <p class="mb-0 text-truncate text-white-50 "
                                                        style="font-size: 0.75rem; line-height: 1.2;"
                                                        title="{{ $arquivo->description ?? $arquivo->file_name }}">
                                                        {{ $arquivo->description ?? $arquivo->file_name }}
                                                    </p>
                                                </div>
                                                {{-- Adicionei o tamanho aqui também para ficar na mesma linha --}}
                                                <p class="text-white-50 mb-0"
                                                    style="font-size: 0.65rem; white-space: nowrap;">
                                                    {{ number_format($arquivo->file_size / 1024 / 1024, 2) }} MB
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
        </div>

        {{-- Paginação 
        <div class="d-flex justify-content-center mt-4">
            {{ $arquivos->appends(request()->query())->links() }}
        </div> --}}
        @endif

        @include('updatemedia_modal')
        @include('mediaPreviewModal')
    </div>

    @push('scripts')
        <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
        <script>
            function confirmDelete(id, btn) {
                if (confirm('Deseja apagar este arquivo permanentemente da Library?')) {
                    btn.disabled = true;
                    const originalContent = btn.innerHTML;
                    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';

                    let rotaTemplate = "{{ route('media.destroy', ':id') }}";
                    let url = rotaTemplate.replace(':id', id);

                    fetch(url, {
                            method: "DELETE",
                            headers: {
                                "X-CSRF-TOKEN": "{{ csrf_token() }}",
                                "Content-Type": "application/json",
                                "Accept": "application/json"
                            }
                        })
                        .then(res => res.json())
                        .then(data => {
                            if (data.success) {
                                const card = btn.closest('.col-md-3');
                                if (window.jQuery) {
                                    $(card).fadeOut(300, function() {
                                        $(this).remove();
                                    });
                                } else {
                                    card.remove();
                                }
                            } else {
                                alert('Erro: ' + (data.message || 'Erro desconhecido'));
                                btn.disabled = false;
                                btn.innerHTML = originalContent;
                            }
                        })
                        .catch(error => {
                            console.error('Erro:', error);
                            alert('Erro na requisição.');
                            btn.disabled = false;
                            btn.innerHTML = originalContent;
                        });
                }
            }

            function openMediaLocal(filePath) {
                fetch(`/media/open`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                        },
                        body: JSON.stringify({
                            path: filePath
                        }) // Enviando como JSON no corpo
                    })
                    .then(res => res.json())
                    .then(data => {
                        if (!data.success) {
                            alert(data.message);
                        }
                    })
                    .catch(err => console.error("Erro ao abrir Media:", err));
            }

            // 1. Função para abrir e popular o modal
            function updateMediaData(media) {
                // 1. Preenche os inputs editáveis
                document.getElementById('media_id').value = media.id;
                document.getElementById('display_id').innerText = media.id;
                document.getElementById('photo_name').value = media.photo_name || '';
                document.getElementById('photo_gallery').value = media.photo_gallery || '';
                document.getElementById('description').value = media.description || '';
                document.getElementById('origin').value = media.origin || '';
                document.getElementById('private').value = media.private || 0;

                // 2. Preenche os metadados técnicos (Visualização)
                document.getElementById('display_hash').innerText = media.file_hash || 'N/A';
                document.getElementById('display_phash').innerText = media.phash || 'N/A';
                document.getElementById('display_mime').innerText = media.mime_type || 'Desconhecido';
                document.getElementById('display_path').innerText = media.file_path;

                // 3. Formata e preenche o tamanho (bytes para MB)
                const sizeInMB = (media.file_size / (1024 * 1024)).toFixed(2);
                document.getElementById('display_size').innerText = sizeInMB + ' MB';

                // 4. Trata informações de Similaridade
                const simBox = document.getElementById('similarity_info');
                if (media.similar_to_id) {
                    document.getElementById('display_similar_to').innerText = media.similar_to_id;
                    document.getElementById('display_score').innerText = media.similarity_score;
                    simBox.classList.remove('d-none');
                } else {
                    simBox.classList.add('d-none');
                }

                // 5. Abre o modal
                const modal = new bootstrap.Modal(document.getElementById('editMediaModal'));
                modal.show();
            }
            // 2. Listener para interceptar o envio do formulário e usar AJAX
            document.getElementById('editMediaForm').addEventListener('submit', function(e) {
                e.preventDefault();

                const formData = new FormData(this);
                const data = Object.fromEntries(formData.entries());

                fetch('{{ route('media.update') }}', {
                        method: 'PUT',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify(data)
                    })
                    .then(response => response.json())
                    .then(result => {
                        if (result.success) {
                            // Fecha o modal e recarrega a página ou atualiza a linha
                            bootstrap.Modal.getInstance(document.getElementById('editMediaModal')).hide();
                            alert(result.message);
                            location.reload();
                        } else {
                            alert('Erro: ' + (result.message || 'Falha ao atualizar'));
                        }
                    })
                    .catch(error => {
                        console.error('Erro no processamento:', error);
                        alert('Erro técnico ao salvar os dados.');
                    });
            });

                function openFastPreview(url, title, isVideo) {
                    const imgEl = document.getElementById('previewImgElement');
                    const videoEl = document.getElementById('previewVideoElement');
                    const titleEl = document.getElementById('mediaPreviewTitle');

                    // Reset
                    imgEl.classList.add('d-none');
                    videoEl.classList.add('d-none');
                    videoEl.pause();
                    videoEl.src = "";

                    titleEl.innerText = title;

                    if (isVideo) {
                        videoEl.src = url;
                        videoEl.classList.remove('d-none');
                        videoEl.play();
                    } else {
                        imgEl.src = url;
                        imgEl.classList.remove('d-none');
                    }

                    const modal = new bootstrap.Modal(document.getElementById('mediaPreviewModal'));
                    modal.show();

                    // Para o vídeo ao fechar
                    document.getElementById('mediaPreviewModal').addEventListener('hidden.bs.modal', function() {
                        videoEl.pause();
                        videoEl.src = "";
                    }, {
                        once: true
                    });
                }            
        </script>
    @endpush
</x-app-layout>
