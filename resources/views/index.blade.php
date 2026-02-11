<x-app-layout name="Mídia FotoFlow">
    <div class="content">
        <div class="container-fluid">
            @if ($arquivos->isEmpty())
                <div class="alert alert-info">Nenhum arquivo encontrado na biblioteca.</div>
            @else
                <div class="row g-3">
                    @foreach ($arquivos as $arquivo)
                        <div class="col-md-3 col-sm-6">
                            <div class="card h-100 shadow-sm border-light">

                                {{-- CONTAINER DA IMAGEM/VIDEO --}}
                                <div class="card-img-container bg-dark"
                                    style="min-height: 200px; position: relative; display: flex; align-items: center; justify-content: center;">

                                    @php
                                        // Definição das variáveis de controle de extensão
                                        $extensao = strtolower(pathinfo($arquivo->file_path, PATHINFO_EXTENSION));
                                        $isVideo = in_array($extensao, [
                                            'mp4',
                                            'mov',
                                            'avi',
                                            'wmv',
                                            'flv',
                                            'mkv',
                                            'webm',
                                        ]);
                                        // MKV e MP4 costumam rodar bem no Chrome/Edge modernos
                                        $nativelySupportedVideo = in_array($extensao, ['mp4', 'webm', 'ogg', 'mkv']);
                                    @endphp
                                    {{-- Renderização da Mídia --}}
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
                                            <div onclick="openFastPreview('{{ route('media.stream', ['path' => $arquivo->file_path]) }}', '{{ $arquivo->title }}', true)"
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
                                                    ({{ strtoupper($extensao) }})
                                                </p>
                                            </div>
                                        @endif
                                    @else
                                        {{-- Clique na Imagem abre o Preview Rápido no Modal --}}
                                        @php
                                         $media_file=asset('storage/' . $arquivo->file_path); 
                                        // dd($media_file);  
                                        @endphp
<img src="{{ route('media.stream', ['path' => $arquivo->file_path]) }}"
     onclick="openFastPreview('{{ route('media.stream', ['path' => $arquivo->file_path]) }}', '{{ $arquivo->title }}', false)"
     class="w-100" 
     style="height: 200px; object-fit: contain; cursor: pointer;"
     loading="lazy" 
     title="Clique para Preview Rápido">
                                    @endif
                                    {{-- OVERLAY COM NOME DO ARQUIVO (Aparece no Hover) --}}
                                    <div class="file-name-overlay text-truncate" title="{{ $arquivo->title }}">
                                        {{ $arquivo->title }}
                                    </div>

                                    {{-- BOTÕES SOBREPOSTOS --}}
                                    <div
                                        style="position: absolute; top: 10px; right: 10px; z-index: 20; display: flex; flex-direction: column; gap: 8px;">

                                        {{-- Botão Visualizar (VLC/IrfanView) - Destaque maior se for FLV --}}
                                        <button
                                            class="btn btn-light btn-sm shadow-sm d-flex align-items-center justify-content-center border"
                                            onclick="openMediaLocal('{{ addslashes($arquivo->file_path) }}')"
                                            title="Visualizar Arquivo Localmente"
                                            style="width: 25px; height: 25px; border-radius: 5px; opacity: {{ $extensao === 'flv' ? '1' : '0.5' }}; transition: opacity 0.3s;">
                                            <i class="bi bi-eye" style="font-size: 0.9rem;"></i>
                                        </button>

                                        {{-- Botão Update --}}
                                        <button
                                            class="btn btn-light btn-sm shadow-sm d-flex align-items-center justify-content-center border"
                                            onclick="updateMediaData({{ json_encode($arquivo) }})"
                                            title="Editar Informações da Mídia"
                                            style="width: 25px; height: 25px; border-radius: 5px; opacity: 0.5; transition: opacity 0.3s;">
                                            <i class="bi bi-pencil-square" style="font-size: 0.9rem;"></i>
                                        </button>

                                        <button class="btn btn-sm btn-outline-info" title="Definir Capa"
                                            onclick="">
                                            <i class="bi bi-person-bounding-box"></i>
                                        </button>

                                        {{-- Botão Favorito --}}
                                        <button
                                            class="btn btn-light btn-sm shadow-sm d-flex align-items-center justify-content-center border"
                                            onclick="toggleFavorite({{ $arquivo->id }})" title="Marcar como Favorito"
                                            style="width: 25px; height: 25px; border-radius: 5px; opacity: {{ $arquivo->is_favorite ? '1' : '0.5' }}; transition: opacity 0.3s;">
                                            <i class="bi {{ $arquivo->is_favorite ? 'bi-star-fill' : 'bi-star' }}"
                                                style="font-size: 0.9rem; color: {{ $arquivo->is_favorite ? '#ffc107' : '' }};"></i>
                                        </button>

                                        {{-- Botão Excluir --}}
                                        <button
                                            class="btn btn-danger btn-sm shadow-sm d-flex align-items-center justify-content-center"
                                            onclick="confirmDelete({{ $arquivo->id }}, this)" title="Excluir"
                                            style="width: 25px; height: 25px; border-radius: 5px; opacity: 0.5; transition: opacity 0.3s;">
                                            <i class="bi bi-trash" style="font-size: 0.8rem;"></i>
                                        </button>

                                        @if ($isVideo)
                                            <span class="badge bg-dark opacity-75"
                                                style="font-size: 0.6rem;">VIDEO</span>
                                        @endif
                                    </div>
                                </div>

                                {{-- CARD BODY (DESCRIÇÃO E TAMANHO) --}}
                                <div class="card-body p-1 px-2">
                                    <div class="d-flex align-items-center justify-content-between gap-2">
                                        <div class="flex-grow-1 text-truncate" style="min-width: 0;">
                                            <p class="card-text mb-0 text-truncate"
                                                style="font-size: 0.75rem; line-height: 1.2;"
                                                title="{{ $arquivo->description ?? $arquivo->file_name }}">
                                                {{ $arquivo->description ?? $arquivo->file_name }}
                                            </p>
                                        </div>
                                        <p class="text-muted mb-0" style="font-size: 0.65rem; white-space: nowrap;">
                                            {{ number_format($arquivo->file_size / 1024 / 1024, 2) }} MB
                                        </p>
                                    </div>
                                </div>

                            </div> {{-- Fim do Card --}}
                        </div> {{-- Fim da Coluna --}}
                    @endforeach
                </div>

                {{-- Paginação --}}
                <div class="d-flex justify-content-center mt-4">
                    {{ $arquivos->appends(request()->query())->links() }}
                </div>
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
                    document.getElementById('title').value = media.title || '';
                    document.getElementById('media_gallery').value = media.media_gallery || '';
                    document.getElementById('description').value = media.description || '';
                    document.getElementById('source_event').value = media.source_event || '';
                    document.getElementById('is_private').value = media.is_private || 0;

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


                function toggleFavorite(id) {
                    const btn = event.currentTarget;
                    const icon = btn.querySelector('i');

                    fetch(`/media/favorite/${id}`, {
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                                'Accept': 'application/json'
                            }
                        })
                        .then(res => res.json())
                        .then(data => {
                            if (data.success) {
                                if (data.is_favorite) {
                                    icon.classList.replace('bi-star', 'bi-star-fill');
                                    icon.style.color = '#ffc107'; // Dourado
                                    btn.style.opacity = '1';
                                } else {
                                    icon.classList.replace('bi-star-fill', 'bi-star');
                                    icon.style.color = '';
                                    btn.style.opacity = '0.5';

                                    // Se estiver na página de favoritos, pode remover o card visualmente
                                    if (window.location.pathname.includes('favorites')) {
                                        btn.closest('.card-container').fadeOut();
                                    }
                                }
                            }
                        });
                }

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
