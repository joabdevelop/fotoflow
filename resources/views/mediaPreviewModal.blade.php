            {{-- Modal de Preview de Imagem --}}
            <div class="modal fade preview-dark" id="mediaPreviewModal" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered" style="max-width: fit-content;"> {{-- Ajuste automático de largura --}}
                    <div class="modal-content bg-dark border-0 shadow-lg" style="width: auto; margin: auto;">
                        <div class="modal-header border-0 p-2"
                            style="position: absolute; width: 100%; z-index: 10; background: linear-gradient(to bottom, rgba(0,0,0,0.7), transparent);">
                            <h5 class="modal-title text-white small" id="mediaPreviewTitle"></h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                                aria-label="Close"></button>
                        </div>
                        <div class="modal-body p-0 d-flex align-items-center justify-content-center"
                            style="background: #000;">
                            {{-- Imagem --}}
                            <img src="" id="previewImgElement" class="d-none"
                                style="max-height: 90vh; max-width: 95vw; width: auto; height: auto; display: block;">

                            {{-- Vídeo --}}
                            <video id="previewVideoElement" controls class="d-none"
                                style="max-height: 90vh; max-width: 95vw;">
                                <source src="" type="video/mp4">
                            </video>
                        </div>
                    </div>
                </div>
            </div>
            {{-- Fim do Modal de Preview de Imagem --}}            
