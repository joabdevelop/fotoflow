# diagrama de fluxo

sequenceDiagram
    autonumber
    participant D as Delphi Service (Windows)
    participant L as Laravel API (PHP)
    participant P as Python AI (Face/Thumb)
    participant DB as MySQL (Database)

    Note over D: Arquivo detectado na Input Path
    D->>L: POST /store (Multipart: File + Metadata)
    
    activate L
    L->>L: Grava arquivo no Storage (Physical Save)
    
    alt Se Gravação Falhar
        L-->>D: 500 Error (Abort)
    else Se Gravação OK
        L->>P: POST /analyze-face (File Path)
        activate P
        Note right of P: Processamento de IA (~5 seg)
        P->>P: Detecta Rostos + Gera Thumbnail
        P-->>L: JSON {has_face, face_data, thumb_path}
        deactivate P

        L->>DB: CALL sp_find_similar_media(phash, best_dist)
        DB-->>L: Return {similar_id, score}

        L->>DB: INSERT/UPDATE media (Full Data)
        DB-->>L: OK
        
        L-->>D: 200 OK (Success JSON)
    end
    deactivate L

    Note over D: Recebe Sucesso
    D->>D: TFile.Delete(AFile)
    Note over D: Arquivo removido da Input

# EOF

# fluxo de upload manual

graph TD
    A[Início: Upload Manual] --> B{Recebe Arquivos}
    B --> C[Separa Imagens de JSONs]
    C --> D[Loop: Para cada Imagem]
    
    D --> E[Detecta Galeria/Evento via Caminho JS]
    E --> F[Busca JSON correspondente]
    F --> G[Extrai Metadados: Google vs Interno]
    
    G --> H{Verifica MD5 no Banco}
    H -- "Já existe" --> I[Registra em CopiaExata]
    H -- "Novo" --> J[Calcula pHash Binário]
    
    J --> K[Salva Foto + Sidecar.json em /inbound]
    K --> L[Cria registro MediaProcessing: 'pending']
    L --> M[Dispara ProcessMediaJob]
    
    subgraph Fila_Background [Processamento em Segundo Plano]
        M --> N[Job: Altera status para 'processing']
        N --> O[Lê Sidecar.json do Inbound]
        O --> P[Chama MediaStoreService->process]
        P --> Q[Move arquivo p/ local final e deleta temporários]
        Q --> R[Remove registro de MediaProcessing]
    end
    
    R --> S[Fim: Mídia Disponível no App]