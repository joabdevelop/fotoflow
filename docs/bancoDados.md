# Documentação Técnica: Estrutura de Reconhecimento Facial (PostgreSQL)

Este documento detalha a arquitetura do banco de dados integrada ao sistema de reconhecimento facial, permitindo o armazenamento de múltiplas faces por imagem e busca vetorial de alta performance.

---

## 1. Requisitos de Ambiente
- **Banco de Dados:** PostgreSQL 13+
- **Extensão:** `pgvector` (necessária para o campo `vector(512)`)
- **Modelos de IA:** RetinaFace (Detecção) e ArcFace (Embedding)
- **Configuração de Match:** `BestDist = 0.62` (definido via arquivo .ini)

---

## 2. Tabelas do Sistema

### 2.1 Tabela Existente: `media_files`
Repositório central de arquivos de mídia.

ID              INTEGER
PHOTO_GALLERY   TEXT
ORIGIN          TEXT
PHOTO_NAME      TEXT
DESCRIPTION     TEXT
FILE_HASH       TEXT
PHASH           TEXT
SIMILARITY_SCORE INT
SIMILAR_TO_ID    INT
FILE_PATH       TEXT
MIME_TYPE       TEXT
FILE_SIZE       BIGINT
FILE_EXTENSION  TEXT
IS_FAVORITE     BOOLEAN
IS_PRIVATE      BOOLEAN
CREATED_AT      TIMESTAMP WITHOUT ZONE

### 2.2 Nova Tabela: `faces`
Armazena os dados espaciais e biométricos de cada face detectada individualmente.

```sql
CREATE EXTENSION IF NOT EXISTS vector;

CREATE TABLE faces (
    id SERIAL PRIMARY KEY,
    media_file_id INTEGER NOT NULL REFERENCES media_files(id) ON DELETE CASCADE,
    
    -- Metadados de Visualização
    thumbnail_path TEXT,          -- Caminho do recorte facial (crop)
    x INTEGER NOT NULL,           -- Coordenada X inicial
    y INTEGER NOT NULL,           -- Coordenada Y inicial
    w INTEGER NOT NULL,           -- Largura do recorte
    h INTEGER NOT NULL,           -- Altura do recorte

    -- Vetor Biométrico (Busca Vetorial)
    embedding vector(512) NOT NULL, -- Dimensões do modelo ArcFace
    embedding_model TEXT DEFAULT 'ArcFace',

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE face_matches (
    id SERIAL PRIMARY KEY,
    face_id_1 INTEGER NOT NULL REFERENCES faces(id) ON DELETE CASCADE,
    face_id_2 INTEGER NOT NULL REFERENCES faces(id) ON DELETE CASCADE,
    
    distance DOUBLE PRECISION NOT NULL,  -- Distância de Cosseno calculada
    threshold DOUBLE PRECISION NOT NULL, -- Valor BestDist usado (ex: 0.62)
    is_match BOOLEAN NOT NULL,           -- Resultado da comparação
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT unique_face_match UNIQUE (face_id_1, face_id_2)
);

-- Índices de Performance
CREATE INDEX idx_faces_media_file ON faces(media_file_id);
CREATE INDEX idx_faces_embedding_cosine ON faces USING hnsw (embedding vector_cosine_ops);


==============================
-- Garante que o usuário tem acesso total ao banco v3
ALTER DATABASE media_data_v3 OWNER TO mediahashservice;

-- Tabela de Faces (Versão Nativa)
CREATE TABLE IF NOT EXISTS public.faces (
    id SERIAL PRIMARY KEY,
    media_file_id INTEGER NOT NULL REFERENCES media_files(id) ON DELETE CASCADE,
    thumbnail_path TEXT,
    x INTEGER NOT NULL,
    y INTEGER NOT NULL,
    w INTEGER NOT NULL,
    h INTEGER NOT NULL,
    embedding float8[] NOT NULL, -- Array nativo (compatível com DeepFace/ArcFace)
    embedding_model TEXT DEFAULT 'ArcFace',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tabela de Matches para cache de comparações
CREATE TABLE IF NOT EXISTS public.face_matches (
    id SERIAL PRIMARY KEY,
    face_id_1 INTEGER REFERENCES faces(id) ON DELETE CASCADE,
    face_id_2 INTEGER REFERENCES faces(id) ON DELETE CASCADE,
    distance DOUBLE PRECISION,
    is_match BOOLEAN,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT unique_face_pair UNIQUE (face_id_1, face_id_2)
);

-- Índice GIN para melhorar buscas em arrays (Nativo do Postgres)
CREATE INDEX IF NOT EXISTS idx_faces_embedding_gin ON public.faces USING GIN (embedding);