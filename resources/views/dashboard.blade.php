<x-app-layout name="Dashboard FotoFlow">
    <div class="p-6 bg-gray-100 min-h-screen">
        <h2 class="text-2xl font-bold mb-4 text-gray-800">Resumo da Biblioteca v3.0</h2>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-8">
            @foreach ($stats as $item)
                <div class="container-fluid mb-4">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <div class="card border-0 shadow-sm bg-primary text-white h-100">
                                <div class="card-body d-flex align-items-center">
                                    <div class="flex-shrink-0 bg-white bg-opacity-25 p-3 rounded">
                                        <i class="bi bi-stack fs-3"></i>
                                    </div>
                                    <div class="ms-3">
                                        <h6 class="card-title mb-1 opacity-75">Total da Biblioteca</h6>
                                        <h3 class="mb-0 fw-bold">{{ $stats->sum('total') }}</h3>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-3">
                            <div class="card border-0 shadow-sm bg-success text-white h-100">
                                <div class="card-body d-flex align-items-center">
                                    <div class="flex-shrink-0 bg-white bg-opacity-25 p-3 rounded">
                                        <i class="bi bi-image fs-3"></i>
                                    </div>
                                    <div class="ms-3">
                                        <h6 class="card-title mb-1 opacity-75">Imagens</h6>
                                        <h3 class="mb-0 fw-bold">{{ $stats->sum('images') }}</h3>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-3">
                            <div class="card border-0 shadow-sm bg-danger text-white h-100">
                                <div class="card-body d-flex align-items-center">
                                    <div class="flex-shrink-0 bg-white bg-opacity-25 p-3 rounded">
                                        <i class="bi bi-play-btn fs-3"></i>
                                    </div>
                                    <div class="ms-3">
                                        <h6 class="card-title mb-1 opacity-75">Vídeos</h6>
                                        <h3 class="mb-0 fw-bold">{{ $stats->sum('videos') }}</h3>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-3">
                            <div class="card border-0 shadow-sm bg-info text-white h-100">
                                <div class="card-body d-flex align-items-center">
                                    <div class="flex-shrink-0 bg-white bg-opacity-25 p-3 rounded">
                                        <i class="bi bi-hdd-fill fs-3"></i>
                                    </div>
                                    <div class="ms-3">
                                        <h6 class="card-title mb-1 opacity-75">Espaço em Disco</h6>
                                        <h3 class="mb-0 fw-bold">
                                            @php
                                                $bytes = $totalSize ?? 0;
                                                $units = ['B', 'KB', 'MB', 'GB', 'TB'];
                                                for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
                                                    $bytes /= 1024;
                                                }
                                                echo round($bytes, 2) . ' ' . $units[$i];
                                            @endphp
                                        </h3>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <h3 class="text-xl font-semibold mb-3 text-gray-700">Últimas 20 Importações</h3>

        <div class="d-flex overflow-x-auto pb-3" style="gap: 15px;">
@foreach ($recentImages as $img)
    <div class="card bg-dark text-white border-0" style="min-width: 180px; height: 250px;">
        <div class="h-100 position-relative bg-black rounded overflow-hidden">
            {{-- Usamos o 'file_path' em vez do 'id' --}}
            @if (str_contains($img->mime_type, 'image'))
                <img src="{{ route('media.stream', ['path' => $img->file_path]) }}"
                     class="w-100 h-100 object-fit-cover">
            @else
                <video class="w-100 h-100 object-fit-cover" preload="metadata">
                    <source src="{{ route('media.stream', ['path' => $img->file_path]) }}#t=0.1"
                            type="{{ $img->mime_type }}">
                </video>
                <div class="position-absolute top-50 start-50 translate-middle">
                    <i class="bi bi-play-circle-fill fs-1 text-white opacity-75"></i>
                </div>
            @endif
        </div>
    </div>
@endforeach
        </div>

        <div class="mt-5 p-4 bg-white rounded shadow-sm">
            <h3 class="text-xl font-bold text-dark border-bottom pb-2 mb-4">
                <i class="bi bi-person-bounding-box me-2"></i>Galeria de Rostos Detectados
            </h3>

            @if ($faces->isEmpty())
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-hourglass-split fs-1 d-block mb-3"></i>
                    <p>Nenhum rosto detectado ainda. O Job de IA pode estar processando...</p>
                </div>
            @else
                <div class="row row-cols-2 row-cols-sm-3 row-cols-md-4 row-cols-lg-6 g-3">
                    @foreach ($faces as $face)
                        <div class="col">
                            <div class="card h-100 shadow-sm border-0 group position-relative overflow-hidden">
                                <img src="{{ Storage::url($face->thumbnail_path) }}"
                                    class="card-img-top object-fit-cover"
                                    style="height: 150px; transition: transform .3s;"
                                    onmouseover="this.style.transform='scale(1.1)'"
                                    onmouseout="this.style.transform='scale(1)'" alt="Rosto detectado">

                                <div class="card-body p-2 text-center bg-light">
                                    <small class="text-muted d-block" style="font-size: 0.7rem;">
                                        ID Mídia: #{{ $face->media_file_id }}
                                    </small>
                                    <a href="{{ route('media.stream', ['id' => $face->media_file_id]) }}"
                                        target="_blank" class="btn btn-sm btn-outline-primary mt-1 py-0 px-2"
                                        style="font-size: 0.65rem;">
                                        Ver Original
                                    </a>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="mt-4">
                    {{ $faces->links() }}
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
