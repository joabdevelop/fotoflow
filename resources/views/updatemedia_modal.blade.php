<div class="modal fade" id="editMediaModal" tabindex="-1" aria-labelledby="editMediaModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form id="editMediaForm" method="POST">
                @csrf
                @method('PUT')
                <input type="hidden" id="media_id" name="id">

                <div class="modal-header bg-dark text-white">
                    <h5 class="modal-title" id="editMediaModalLabel">Editar Informações da Mídia</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                        aria-label="Close"></button>
                </div>

                <div class="modal-body" style="background-color: #c2dfd5;">
                    <div class="row">
                        {{-- Seção 1: Campos Editáveis (Semânticos) --}}
                        <div class="col-md-7">
                            <div class="mb-3">
                                <label class="form-label fw-bold">Nome da Foto/Mídia</label>
                                <input type="text" class="form-control" id="title" name="title">
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-bold">Galeria (Álbum)</label>
                                <input type="text" class="form-control" id="media_gallery" name="media_gallery">
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-bold">Descrição</label>
                                <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label fw-bold">Origem</label>
                                        <input type="text" name="source_event" id="source_event" class="form-control">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label fw-bold">Privacidade</label>
                                        <select class="form-select" id="is_private" name="is_private">
                                            <option value="0">Público</option>
                                            <option value="1">Privado</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- Seção 2: Metadados Técnicos (Read-Only) --}}
                        <div class="col-md-5 bg-light p-3 rounded">
                            <h6 class="text-muted border-bottom pb-2">Metadados Técnicos</h6>

                            <div class="small">
                                <p class="mb-1"><strong>Id:</strong> <br><span id="display_id"
                                        class="text-break text-muted"></span></p>

                                <p class="mb-1"><strong>Hash MD5:</strong> <br>
                                    <span id="display_hash" class="text-break text-muted"
                                        style="word-break: break-all;"></span>
                                </p>
                                <p class="mb-1"><strong>pHash:</strong> <br>
                                    <span id="display_phash" class="text-muted"
                                        style="word-break: break-all; display: block; max-width: 100%;"></span>
                                </p>
                                <p class="mb-1"><strong>Formato:</strong> <span id="display_mime"
                                        class="badge bg-secondary"></span></p>
                                <p class="mb-1"><strong>Tamanho:</strong> <span id="display_size"
                                        class="text-muted"></span></p>
                                <p class="mb-1"><strong>Caminho:</strong> <br><small id="display_path"
                                        class="text-muted text-break"></small></p>

                                <div id="similarity_info"
                                    class="mt-2 p-2 bg-warning bg-opacity-10 border border-warning rounded d-none">
                                    <strong>Similar a ID:</strong> <span id="display_similar_to"></span><br>
                                    <strong>Score:</strong> <span id="display_score"></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer" style="background-color: #038d5a;">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Salvar Alterações</button>
                </div>
            </form>
        </div>
    </div>
</div>
