# Laudo Vet Generator

Gerador de **laudos de ultrassonografia veterinária abdominal**. Substitui o fluxo manual
(editar `.docx` apagando opções) por um formulário rápido, orientado a teclado, que produz um
**PDF padronizado** com o cabeçalho do paciente, a prosa clínica composta a partir das seleções
e as imagens anexadas.

> **Princípio central:** o laudo **não** é texto livre. O frontend coleta apenas **seleções +
> medidas** (um JSON); o backend PHP guarda os **fragmentos de frase** e compõe o parágrafo final
> de cada órgão. Trocar a redação = mexer só nos compositores PHP, sem tocar no schema nem no
> formulário.

---

## Sumário

- [Arquitetura](#arquitetura)
- [Como rodar (Docker)](#como-rodar-docker)
- [Como rodar (sem Docker)](#como-rodar-sem-docker)
- [Testes](#testes)
- [A API](#a-api)
- [Estrutura do projeto](#estrutura-do-projeto)
- [Como a composição funciona](#como-a-composição-funciona)
- [Convenções e governança](#convenções-e-governança)

---

## Arquitetura

```
┌──────────────┐   JSON (schema)   ┌───────────────────────────┐   texto    ┌──────────────┐
│  Frontend    │ ────────────────▶ │  API (public/index.php)   │ ─────────▶ │  PDF (FPDF)  │
│  HTML/CSS/JS │   seleções +      │  handler → montarLaudo()  │  composto  │  + imagens   │
│  (vanilla)   │   medidas + JPEG  │  → gerarLaudoPdf()        │            │  JPEG        │
└──────────────┘                   └───────────────────────────┘            └──────────────┘
```

| Camada       | Tecnologia                          | Papel                                                        |
|--------------|-------------------------------------|--------------------------------------------------------------|
| Frontend     | HTML5 + CSS3 + JS puro (vanilla)     | Formulário por órgão; coleta seleções/medidas; monta o JSON. |
| API          | PHP 8.3                             | Valida o payload, compõe o laudo e gera o PDF.               |
| Composição   | Compositores PHP por órgão           | Donos dos fragmentos de frase (a prosa mora aqui).           |
| Geração PDF  | [FPDF](https://www.fpdf.org/) 1.8    | Texto + anexo de JPEGs (efêmeros, descartados após gerar).   |

O **contrato** entre frontend e backend é o schema JSON: `docs/referencia/laudo.schema.json`.

---

## Como rodar (Docker)

Pré-requisito: Docker (com Docker Compose).

```bash
docker compose up --build
```

Abra **http://localhost:8080** — o formulário do laudo é servido no `GET /`.
Preencha, clique em **Gerar PDF** e o download começa.

Sem Compose:

```bash
docker build -t laudo-vet-generator .
docker run --rm -p 8080:8080 laudo-vet-generator
```

> **Windows:** o Docker Desktop usa o backend **WSL2**. Se aparecer "virtualisation support
> wasn't detected", confirme a virtualização habilitada na BIOS/UEFI e habilite o WSL2: abra o
> **PowerShell como Administrador**, rode `wsl --install` e **reinicie**. Sem Docker, use o modo
> local acima (`iniciar.bat`).

---

## Como rodar (sem Docker)

Pré-requisitos: **PHP 8.3** com as extensões `mbstring`, `gd` e `zip`, e o
[Composer](https://getcomposer.org/).

```bash
composer install
php -S localhost:8080 -t public
```

Abra **http://localhost:8080**.

> **Windows (atalho):** dê **duplo-clique em `iniciar.bat`** — ele sobe o servidor e abre o
> navegador. Usa o PHP do sistema (ou um PHP local em `.tooling/`, se existir).

> `gd` é usado apenas pelos testes (para fabricar JPEGs de amostra); o app em si usa `getimagesize`,
> que é do core. `mbstring` é necessário na geração do PDF (conversão UTF-8 → Windows-1252).

---

## Testes

Os testes são scripts PHP de *round-trip*: montam um payload, chamam o compositor/agregador e
comparam com o texto esperado (extraído do modelo/laudo real).

**Com Docker:**

```bash
docker compose run --rm laudo php tests/all.php
```

**Sem Docker:**

```bash
php tests/all.php          # roda a suíte inteira e agrega o resultado
```

Rodar um único teste:

```bash
php tests/laudo-round-trip.php
```

---

## A API

Um único endpoint em `public/index.php`:

| Método | Rota        | Descrição                                              |
|--------|-------------|--------------------------------------------------------|
| `GET`  | `/`         | Serve o formulário (HTML).                             |
| `GET`  | `/?health`  | JSON de status do serviço.                             |
| `POST` | `/`         | Recebe o payload JSON e devolve o **PDF** do laudo.    |

**Entrada** (`POST`, `application/json`): o payload segue `docs/referencia/laudo.schema.json`.
As imagens são opcionais, no campo `imagens` — uma lista de **JPEG em base64** (aceita também
data URI). Elas são efêmeras: usadas só para montar o PDF e **não** ficam no servidor.

**Saída:** `200` com `Content-Type: application/pdf` (nome do arquivo pelo paciente), ou
`400` (payload inválido) / `405` (método) / `500` com `{ "error": "..." }`.

Exemplo:

```bash
curl -X POST http://localhost:8080/ \
  -H "Content-Type: application/json" \
  -o laudo.pdf \
  -d '{
    "cabecalho": {
      "paciente": "Clarinha", "especie": "Canino", "sexo": "F", "idade": "7 anos",
      "responsavel": "Bruna", "veterinario_requisitante": "Dra. Jaqueline",
      "data_exame": "2026-06-30", "data_laudo": "2026-07-02"
    },
    "orgaos": {
      "bexiga": { "avaliado": true, "replecao": "discreta",
                  "parede": { "aspecto": "normoespessa", "espessura_cm": 0.11 } },
      "reprodutor": { "utero_ovarios_ausentes": { "avaliado": true } }
    },
    "impressao_diagnostica": ["Hérnia umbilical."],
    "observacoes_finais": [],
    "imagens": []
  }'
```

---

## Estrutura do projeto

```
public/
  index.php              # endpoint: GET serve o form, POST gera o PDF
  app.html               # formulário
  assets/
    app.js               # form data-driven (espelha o schema), Tudo Normal, POST/download
    styles.css           # estilos (tema claro/escuro, navegação por teclado)
src/
  laudo.php              # montarLaudo(): agrega cabeçalho + órgãos + impressão + rodapé
  api/handler.php        # tratarRequisicaoLaudo(): validação + orquestração (função pura)
  pdf/gerarPdf.php        # gerarLaudoPdf(): texto + anexo de JPEGs (FPDF)
  composers/
    _helpers.php         # formatarCm, grauAdverbio, grauAdjetivo, faixaCm, ...
    bexiga.php, rins.php, adrenais.php, figado.php, vesicula-biliar.php,
    baco.php, estomago.php, intestinos.php, pancreas.php, reprodutor.php,
    cavidade-abdominal.php
tests/
  *-round-trip.php       # um teste por órgão + agregador + PDF + API
docs/referencia/
  laudo.schema.json          # contrato (fonte de verdade da estrutura)
  modelo-laudo-aline.txt     # modelo com todas as opções (fonte de verdade da redação)
  exemplo-laudo-clarinha.txt # laudo real preenchido (caso de validação)
Dockerfile, docker-compose.yml, composer.json
```

---

## Como a composição funciona

1. **Schema** (`laudo.schema.json`) — descreve, por órgão, os campos de seleção (enums),
   medidas (cm) e o valor "normal" de cada campo (alimenta o botão **Tudo Normal**).
2. **Compositores** (`src/composers/<orgao>.php`) — cada um expõe `compose<Orgao>(array $x): string`
   e é o dono da prosa daquele órgão. Trata órgão não avaliado, medidas nulas (somem da frase),
   achados múltiplos com lateralidade e concordância de gênero/número.
3. **Agregador** (`src/laudo.php`) — `montarLaudo()` chama os compositores na ordem canônica,
   monta cabeçalho, impressão diagnóstica, observações e o rodapé fixo da médica.
4. **PDF** (`src/pdf/gerarPdf.php`) — renderiza o texto e anexa as imagens JPEG.

A redação segue o **modelo canônico** (`modelo-laudo-aline.txt`). O laudo de exemplo
(`exemplo-laudo-clarinha.txt`) é apenas **um paciente**, usado para validar o *round-trip* — não é
o template.

---

## Convenções e governança

- **Nomenclatura:** arquivos/pastas em `kebab-case`; chaves JSON em `snake_case` (pt-BR sem
  acentos); IDs de campo prefixados pelo órgão (`bexiga_replecao`).
- **Git:** branch base imaculada (só recebe *merges*); uma branch de feature por etapa;
  [Conventional Commits](https://www.conventionalcommits.org/) atômicos (`feat:`, `fix:`,
  `refactor:`, `docs:`, `chore:`, `build:`, `test:`).
- **Schema:** mudanças **aditivas** (campos opcionais com default) para não quebrar o
  contrato nem o "Tudo Normal".

---

_Rodapé fixo dos laudos — Aline Marques de Souza · Ultrassonografia Veterinária · CRMV-MG 31116 ·
Pouso Alegre/MG._
