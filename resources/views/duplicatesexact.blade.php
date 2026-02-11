<x-app-layout>
    <div class="container py-5">
        <h3>Validação de Duplicatas Exatas (MD5)</h3>
        <p class="text-muted mb-4">Cada linha mostra o arquivo original e suas respectivas cópias encontradas.</p>

        @foreach ($originals as $original)
            <div class="card mb-5 shadow-sm border-primary">
                <div class="card-header bg-primary text-white d-flex justify-content-between">
                    <span><strong>Original ID: #{{ $original->id }}</strong> | {{ $original->photo_name }}</span>
                    <span>Hash: <code class="text-white">{{ $original->hash }}</code></span>
                </div>

                <div class="row g-0 p-3 bg-white">
                    <div class="col-md-4 border-end">
                        <div class="p-2 text-center">
                            <span class="badge bg-success mb-2">Original Preservado</span>
                            @if (str_contains($original->mime_type, 'video'))
                                <video src="{{ route('media.stream', ['path' => $original->file_path]) }}"
                                    class="preview-img-lg" controls></video>
                            @else
                                <img src="{{ route('media.stream', ['path' => $original->file_path]) }}"
                                    class="preview-img-lg">
                            @endif
                            <div class="mt-2 small text-truncate"><strong>Caminho:</strong> {{ $original->file_path }}
                            </div>
                            <div class="text-muted small">{{ number_format($original->file_size / 1024 / 1024, 2) }} MB
                            </div>
                        </div>
                    </div>

                    <div class="col-md-8 bg-light">
                        <div class="p-2">
                            <span class="badge bg-danger mb-2">Cópias Identificadas
                                ({{ $original->copiasFisicas->count() }})</span>
                            <div class="row row-cols-1 row-cols-md-2 g-3">
                                @foreach ($original->copiasFisicas as $copia)
                                    {{-- 1. Alterado de 'col' para 'col-md-6' ou 'col-12' para dar mais largura na grade --}}
                                    <div class="col-md-6 mb-3">
                                        <div class="card h-100 border-dashed shadow-sm">
                                            <div class="card-body p-0"> {{-- Removido d-flex para empilhar verticalmente como o original --}}

                                                {{-- 2. Container de Mídia ampliado --}}
                                                <div class="bg-dark"
                                                    style="width: 100%; height: 250px; display: flex; align-items: center; justify-content: center; overflow: hidden; position: relative;">
                                                    @php
                                                        $ext = strtolower(
                                                            pathinfo($copia->file_path, PATHINFO_EXTENSION),
                                                        );
                                                        $isVideo = in_array($ext, [
                                                            'mp4',
                                                            'mov',
                                                            'avi',
                                                            'wmv',
                                                            'mkv',
                                                            'flv',
                                                        ]);
                                                    @endphp

                                                    @if ($isVideo)
                                                        <video
                                                            src="{{ route('media.stream', ['path' => $copia->file_path]) }}#t=0.1"
                                                            style="width: 100%; height: 100%; object-fit: contain;"
                                                            controls muted preload="metadata">
                                                        </video>
                                                        <div style="position: absolute; top: 10px; right: 10px;">
                                                            <span class="badge bg-dark opacity-75"><i
                                                                    class="bi bi-film"></i> VIDEO</span>
                                                        </div>
                                                    @else
                                                        <img src="{{ route('media.stream', ['path' => $copia->file_path]) }}"
                                                            style="width: 100%; height: 100%; object-fit: contain;"
                                                            loading="lazy">
                                                    @endif
                                                </div>

                                                {{-- 3. Informações abaixo da imagem --}}
                                                <div class="p-3">
                                                    <div class="text-truncate mb-1" title="{{ $copia->file_name }}">
                                                        <strong><i class="bi bi-file-earmark"></i>
                                                            {{ $copia->file_name }}</strong>
                                                    </div>
                                                    <div class="text-muted mb-3"
                                                        style="font-size: 0.75rem; word-break: break-all;">
                                                        {{ $copia->file_path }}
                                                    </div>

                                                    <div class="d-flex gap-2">
                                                        {{-- Botão de Excluir destacado --}}
                                                        <button class="btn btn-outline-danger btn-sm w-100"
                                                            onclick="confirmDeleteExact({{ $copia->id }}, this)">
                                                            <i class="bi bi-trash"></i> Excluir esta cópia
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    @push('scripts')
        <script>
            function confirmDeleteExact(id, btn) {
                if (!btn) {
                    console.error("Botão não identificado.");
                    return;
                }

                if (confirm('Deseja apagar esta cópia física permanentemente?')) {
                    btn.disabled = true;
                    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';

                    // Monta a URL usando o ID
                    let rotaTemplate = '{{ route('duplicates.exact.delete', ':id') }}';
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
                                // Remove o card da tela (procura a coluna pai)
                                let card = btn.closest('[class*="col"]');
                                if (card) {
                                    card.style.transition = 'opacity 0.4s';
                                    card.style.opacity = '0';
                                    setTimeout(() => card.remove(), 400);
                                }
                            } else {
                                alert('Erro: ' + data.message);
                                btn.disabled = false;
                                btn.innerHTML = '<i class="bi bi-trash"></i> Excluir';
                            }
                        })
                        .catch(error => {
                            console.error('Erro:', error);
                            btn.disabled = false;
                            btn.innerHTML = '<i class="bi bi-trash"></i> Excluir';
                        });
                }
            }
        </script>
    @endpush

    @push('styles')
        <style>
            .preview-img-lg {
                width: 100%;
                height: 250px;
                object-fit: contain;
                background: #000;
                border-radius: 4px;
            }

            .border-dashed {
                border: 1px dashed #dee2e6;
            }
        </style>
    @endpush
</x-app-layout>
