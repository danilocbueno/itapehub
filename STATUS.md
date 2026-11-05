# Status do projeto: ItapêHub

*Atualizado em 05/11/2026*

Agregador de notícias de Itapetininga-SP. O GitHub Actions coleta as fontes locais várias vezes por dia e publica um site estático no GitHub Pages.

- **No ar:** https://danilocbueno.github.io/itapenews/
- **Repositório:** danilocbueno/itapenews (público)
- **Início:** 25/10/2026, com 47 commits de código até agora (sem contar as coletas automáticas)

## Números

| | |
|---|---|
| Testes | **185 passando** (572 asserções), sem acesso à rede |
| Código | ~3.100 linhas em `app/`, ~1.700 em `tests/` |
| Fontes ativas | 10 |
| Notícias salvas | 250 (185 em outubro e 65 até 05/11) |
| Temas | 10 (Empregos, Polícia, Trânsito, Clima, Esportes, Saúde, Educação, Política, Cultura, Outras) |
| Qualidade | `composer check` = Pint + Larastan nível 6 + Pest |

## Stack

PHP 8.4 · Laravel Zero 13 (binário `itape`) · Blade · Laravel Http (pool e streaming) · SimplePie · Symfony DomCrawler · Pest · Larastan · Pint. O projeto não tem servidor nem banco de dados: os dados ficam em JSON versionado.

## Fontes

| Fonte | Coleta | Filtro "Itapetininga" |
|---|---|---|
| g1 Itapetininga e região | RSS | sim |
| Jornal de Itapetininga | RSS (ISO-8859-1) | não |
| Correio de Itapetininga | Sitemap (lê só os primeiros 40 KB) | não |
| Prefeitura (selo Oficial) | HTML da home | não |
| Folha de Itapetininga | HTML da home | não |
| TVS+ (SBT) | RSS da busca | sim |
| Câmara Municipal (selo Oficial) | RSS | não |
| Xeh | RSS | sim |
| Jornal Panorâmico | RSS | sim |
| Olá Itapetininga | RSS | sim |

Fontes descartadas: Google News (decisão do projeto), Cruzeiro do Sul (responde 403), PortalR3 (parou de cobrir Itapetininga) e Cidade Itapetininga (fora do ar).

## O que já está implementado

### Coleta (`php itape collect`)
- Busca paralela das fontes com ETag e Last-Modified. Uma fonte que falha não derruba as outras.
- Três adaptadores: `rss`, `html_list` e `sitemap_stream`.
- Para itens sem imagem, resumo ou data, abre a página da notícia e lê as tags `og:*` ou seletores CSS (limite de 40 páginas por execução).
- Corrige títulos cortados (reticências) com o `h1` da página. Também repara até 10 notícias antigas por execução.
- Limpeza dos textos:
  - remove HTML, entidades duplas, legendas de foto, o rodapé do WordPress e o marcador "[…]";
  - converte para UTF-8.
- Filtro local: para fontes regionais, só aceita notícias que citam "Itapetininga" no título ou no início do texto.
- Agrupamento da mesma notícia vinda de veículos diferentes, numa janela de 48h ("Também em").
- Previsão do tempo (Open-Meteo) e avisos do INMET para a região.

### Site (`php itape build`)
- **Marca ItapêHub:** "Itapê" em negrito e "Hub" em vermelho, sublinhados por uma linha com quatro paradas que termina num anel. O favicon é o terminal dessa linha. Subtítulo: "Tudo o que acontece na cidade, num só lugar".
- **Cabeçalho da home:** data à esquerda e previsão à direita do nome (no celular, numa linha logo abaixo). O menu fecha com um fio duplo.
- **Home** em estilo de jornal, sem filtro por fonte:
  - manchete e duas notícias ao lado (desktop), depois grade de 3 colunas;
  - "Próximos eventos" em 3 colunas, com o link da agenda na linha do título;
  - o resto do primeiro dia vem com o separador "Hoje", e os dias seguintes com o nome do dia;
  - linha do tempo dos últimos 3 dias.
- **Linha de assuntos** no topo: Agenda e os temas.
- **Páginas de tema** com as 60 notícias mais recentes de cada um.
- **Agenda:** eventos dos próximos 14 dias, extraídos das notícias, com um card por evento. A home também tem o bloco "Próximos eventos".
- **Resumo do dia** (`/hoje/`): texto pronto para colar no WhatsApp, com as notícias das últimas 24h ordenadas pelo número de veículos.
- **Arquivo por dia**, página **Fontes** (status de cada coleta), **feed RSS** agregado e página 404.
- **Tempo:** um dia de cada vez (hoje, amanhã e depois), alternando, com ícone, máxima e mínima e chance de chuva. Avisos do INMET reunidos numa faixa no topo.
- Visual: fontes Newsreader e IBM Plex Sans servidas pelo próprio site, paleta papel/tinta/vermelho e modo escuro. Funciona bem no celular.
- Imagens carregadas direto dos veículos, com `lazy` e `no-referrer`.

### Automação (GitHub Actions)
- **collect.yml:** coleta, build e deploy no Pages. Faz commit dos dados.
  - Horários (UTC): `17 10-23`, `17 0-2` e `17 5,8`, ou seja, de hora em hora das 7h às 23h no horário de Brasília, mais 2h e 5h.
- **ci.yml:** roda Pint, Larastan e Pest a cada push.
- **monitor.yml:** diário. `check-sources --live` testa as fontes reais e avisa quando alguma quebra.

### Configuração por variáveis (sem mexer no código)
`SITE_NAME`, `SITE_TAGLINE`, `SITE_URL`, `SITE_DOMAIN`, `SITE_CONTACT` e `GOATCOUNTER_CODE`.

## Testes por arquivo

| Arquivo | Testes* |
|---|---|
| Feature/BuildCommandTest | 47 |
| Unit/SupportTest | 15 |
| Unit/AdaptersTest | 13 |
| Unit/WeatherTest | 13 |
| Feature/CollectCommandTest | 12 |
| Unit/EventsTest | 11 |
| Unit/FilterAndClusterTest | 11 |
| Unit/ThemesTest | 10 |
| Unit/DigestTest | 6 |
| Unit/PageExtractorTest | 6 |
| Unit/ScheduleTest | 4 |
| Feature/CheckSourcesCommandTest | 3 |

\* Blocos `it()`. Com os datasets, o Pest conta 185 casos.

Os testes usam cópias reais das fontes em `tests/Fixtures/`. Cada bug encontrado vira fixture e teste.

## Em aberto / decisões pendentes

- **Domínio:** o nome ItapêHub foi decidido. Os domínios itapehub.com.br e itapehub.com estavam livres em 04/11; falta registrar.
- **Antes do push:** conferir se a variável `SITE_NAME` está definida no GitHub, porque ela vale mais que o nome padrão.
- **GoatCounter:** falta criar a conta e escolher o código (ex.: `itapehub`).
- **Testado e descartado:** um bloco "Resumo do dia" na home, com destaques e períodos. Saiu porque repetia as notícias da lista logo abaixo; a página `/hoje/` continua.

## Backlog

**Próximos candidatos**
- Estatísticas com GoatCounter (parte 1, só o script).
- "Mais lidas" a partir dos cliques no GoatCounter (parte 2: precisa de um token de API e de tráfego mínimo).
- Agrupamento melhor da mesma notícia com títulos diferentes (ex.: caso "caminhão/carreta com feijão").
- Imagem de compartilhamento própria (card OG).
- Mostrar "atualizado às HH:MM" no rodapé.
- Atualizar as versões das actions do GitHub (aviso do Node 20).
- Reavaliar os horários da coleta.

**Depois**
- Busca.
- Canal no Telegram.
- Domínio próprio.
- Seções Obituário, PAT/Empregos e Concursos.
- Páginas por bairro ou distrito, botão "reportar erro", newsletter, PWA.
- **Fase 2:** resumo semanal no Instagram (rascunho por IA, aprovação humana e cards próprios).

## Como trabalhamos

Mudanças em pedaços pequenos, cada uma com testes novos e `composer check` verde. Cada commit é confirmado antes, e o push só acontece depois da aprovação. A prioridade é não quebrar o que já funciona.

## Comandos úteis

```bash
php itape collect            # coleta (--source=g1, --skip-weather, --force-weather)
php itape build              # gera build/
php itape check-sources      # diagnóstico (--live usa as fontes reais)
composer check               # Pint + Larastan + Pest
php -S localhost:8000 -t build
```
