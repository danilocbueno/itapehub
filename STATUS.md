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
- **Linha de assuntos** no topo: Agenda, Cinema e os temas.
- **Páginas de tema** com as 60 notícias mais recentes de cada um.
- **Agenda:** eventos dos próximos 14 dias, extraídos das notícias, com um card por evento. A home também tem o bloco "Próximos eventos". No topo, o bloco **"Hoje no cinema"** mostra as sessões que ainda vão começar (ou as de amanhã, se as de hoje já acabaram).
- **Cinema** (`/cinema/`): programação do Grupo Cine Itapetininga nos próximos 7 dias, com pôster, classificação, gênero, duração, sala e horários. Cada horário leva à compra no site do cinema, e as sessões que já começaram aparecem riscadas. A programação é lida da página do cinema na coleta, a cada 3 horas, e guardada em `data/cinema.json`. O cinema não tem RSS; o `robots.txt` dele permite uso de referência e proíbe o treino de IA, e o conteúdo não passa por IA.
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

## Notícias das sessões da Câmara (no ar)

- `php itape camara` coleta as sessões do SisCam (presença, votações com a fase, roteiro com autor e texto de cada documento) e escreve uma notícia por sessão encerrada.
  - O texto vem do **Gemini** (`gemini-3.8-flash`, com reserva `gemini-3.5-flash-lite`, cota gratuita), seguindo as regras de `resources/prompts/camara-sessao.md`. Antes de salvar, o código confere os tamanhos e os nomes e o gênero dos vereadores.
  - Se o Gemini faltar ou falhar, entra um **modelo fixo sem IA**, e o Gemini é tentado de novo nas próximas execuções (até 6 vezes).
  - Nomes curtos dos vereadores em `config/camara.php` (revisar).
- Página `/camara/<slug>/`:
  - capa gerada com o número da sessão;
  - aviso de texto automático no começo;
  - lista completa dos documentos com link para o SisCam.
- A notícia também vira card na linha do tempo e no tema Política. O passo roda no workflow com o secret `GEMINI_API_KEY`.
- **Depois (opcional):** transcrição do vídeo da sessão (YouTube da Câmara) para incluir o que foi debatido na tribuna.

## Notícias de programas em vídeo (implementado em 08/11/2026)

**Como funciona:**
- `php itape programas` roda de hora em hora no workflow.
- Lê a aba "Ao vivo" de cada programa em `config/programas.php` e grava os episódios dos últimos 7 dias em `data/programas/<programa>/<videoId>.json`. Programas:
  - **Telescópio** (TV Itapê): entrevista, 1h a 1h40;
  - **Jornal do Meio-Dia** (Rádio Super Difusora): cerca de 1h;
  - **Manhã da Pop** (Pop FM): 3h a 3h40, às vezes em duas partes.
- Nas rádios o vídeo vai com 0,2 quadro por segundo (`fps`): a imagem é só o estúdio, e só assim um programa de 3h30 cabe numa chamada (cerca de 490 mil tokens).
- É escrito um episódio por execução, somando todos os programas: um vídeo longo já passa do limite gratuito de 250 mil tokens por minuto. Recusa por cota (429) não conta como tentativa.
- A notícia é escrita com o Gemini (`gemini-3.5-flash-lite`, reserva `gemini-3.8-flash`), seguindo `resources/prompts/programa-video.md`.
- Antes de publicar, confere:
  - tamanhos;
  - momentos (onde cada assunto do texto é dito no vídeo, para o leitor conferir) com minutos válidos e dentro do vídeo, mostrados em ordem de tempo;
  - nenhum telefone, e-mail, @ ou site no texto;
  - dia da semana certo.
- Se falhar, não publica nada e tenta de novo (até 6 execuções).
- Página `/programas/<programa>/<slug>/`, com o vídeo incorporado, o aviso de IA, o texto, os momentos e os participantes. Card na linha do tempo com a miniatura do YouTube.

**Descoberto em 09/11/2026 (primeiro dia):**
- **Atraso das gravações:** depois que uma live termina, o YouTube processa a gravação por cerca de 12 horas (de madrugada). Até lá, o Gemini responde 403 ("The caller does not have permission"), mesmo com o vídeo público. O 403 é tratado como "ainda não disponível": não conta como tentativa, e o episódio é deixado de lado se continuar assim 3 dias depois de encontrado (`give_up_days`). As notícias dos programas saem no dia seguinte.
- **Horários do GitHub:** o GitHub não roda o workflow de hora em hora. As execuções agendadas saem só 4 ou 5 vezes por dia, a cada 5 ou 6 horas. O Danilo decidiu que tudo bem as notícias saírem no dia seguinte e em horários variados. Se um dia isso importar, dá para disparar o workflow de hora em hora por um Worker do Cloudflare (Cron Trigger chamando a API do GitHub).
- **Recorte de vídeo:** os campos `start_offset` e `end_offset` não funcionam com links do YouTube. O Gemini recebe a página do YouTube ("confirme que você não é um robô") em vez do vídeo.

**Decisões do Danilo:**
- **Páginas lidas:** a aba "Ao vivo" de cada canal e, no Telescópio, também a aba "Vídeos". Alguns episódios saem como vídeo comum, sem live: os de 05 e 06/11. A busca do canal não é usada.
- **Sem episódios antigos:** o recurso começa com os episódios de 09/11/2026 em diante (`since` em `config/itape.php`). Nesta primeira semana o Danilo acompanha a qualidade dos textos.
- **Os três programas são diários (dias úteis):** cerca de 5h45 de vídeo por dia, dentro do limite gratuito de 8 horas de vídeo do YouTube por dia. A folga cobre refazer o Telescópio ou o Jornal, mas não a Pop no mesmo dia; nesse caso o episódio fica para o dia seguinte.

### Planejamento e prova de conceito

Ideia: quando um programa local publicar um episódio novo no YouTube, o ItapêHub escreve uma notícia sobre ele com o Gemini. Começa pelo **Telescópio, da TV Itapê** ([canal](https://www.youtube.com/@tvitape2/videos), id `UCmRTpHDXWhSMOgGXZ-aZMYw`). Depois, PopFM e Rádio Difusora.

**O que descobrimos**
- O Telescópio hoje é um **programa de entrevista ao vivo** (38 min a 1h30), com título no padrão `Telescópio - DD/MM/AAAA - Tema`.
  - A antiga playlist "Telescópio – Agenda Cultural" parou em 02/07/2026.
  - O canal também publica **cortes** curtos ("TELESCÓPIO com…", 10 a 60 s), que devem ser ignorados.
- As **lives não aparecem no RSS do YouTube** (nem no feed do canal, nem no de uploads).
- O **Gemini aceita a URL do YouTube direto** (`{"type":"video","uri":"https://www.youtube.com/watch?v=ID"}`): não é preciso baixar o áudio nem transcrever.
  - Só vídeos públicos.
  - Na cota gratuita, até 8 h de vídeo do YouTube por dia.

**Decisões**
- Detecção pela aba "Ao vivo" do canal (`/@tvitape2/streams`, lendo o `ytInitialData` da página), filtrando pelo padrão do título e por uma duração mínima. A YouTube Data API fica de reserva.
- **Se o Gemini falhar, não publica nada** e tenta de novo na próxima execução. Não há modelo fixo nem card de reserva.
- A página da notícia incorpora o vídeo do YouTube e dá crédito à TV Itapê. O Danilo avisa a emissora.
- A página traz o aviso de texto gerado por IA no começo e os "momentos" com link para o minuto do vídeo (`&t=`).

**Prova de conceito** (`docs/telescopio-poc/`: `poc.php`, `prompt.md` e dois exemplos de saída)
- Rodar na raiz do projeto: `set -a; . ./.env; set +a; php docs/telescopio-poc/poc.php [videoId] [título] [AAAA-MM-DD] [modelo] [agentic|static]`.
- Resultados:

  | Modelo | Resultado |
  |---|---|
  | `gemini-3.5-flash-lite`, modo `static` | Funcionou. Entrega do Amor (38 min): 45 s, ~210 mil tokens. Análise Política (1h29): 65 a 76 s, ~490 mil tokens. |
  | `gemini-3.8-flash` (agentic e static) | Falhou 5 vezes (erro `INTERNAL` ou alta demanda). O 3.6 Flash também estava com alta demanda. |
  | Vídeo curto da documentação | Funcionou no Flash Lite. O caminho está certo; o problema é a carga ou o tamanho do vídeo no 3.8. |

- Ajustes no prompt depois da primeira rodada:
  - proibir chave Pix, conta, telefone, e-mail e @ de rede social (erro de transcrição pode mandar dinheiro para a pessoa errada);
  - cobrir o programa inteiro;
  - texto concreto, com a posição de cada convidado;
  - nome de pessoa só quando estiver no título ou escrito na tela.

**Problemas observados (tratar na implementação)**
1. O modelo errou o dia da semana ("quinta-feira (6)" para 06/11, que é sexta). O código deve calcular o dia e mandar pronto no input.
2. Em programa longo, os "momentos" pararam nos 40 de 89 minutos. Validar que os momentos estão dentro da duração e chegam perto do fim; senão, rejeitar e tentar de novo.
3. Nomes ouvidos no áudio podem vir com a grafia errada ("Helena Lagoeiro", "Edinho Madero", "Cassiano Terra": conferir no vídeo).

**Plano de implementação (em partes, com testes)**
1. `config/programas.php`: canal, padrão do título, duração mínima, nome no chapéu ("TV Itapê · Telescópio") e prompt de cada programa.
2. Detecção: `php itape programas` lê a aba "Ao vivo" e grava os episódios novos em `data/programas/<programa>/<videoId>.json`.
3. Escrita:
   - Gemini, com o Flash Lite como modelo principal para vídeo e o 3.8 Flash de reserva;
   - prompt em `resources/prompts/programa-video.md`;
   - validação (tamanhos, momentos, dia da semana vindo do código);
   - novas tentativas nas execuções seguintes.
4. Site: página `/programas/<programa>/<slug>/` com o vídeo incorporado, o texto e os momentos, card com a miniatura do YouTube e classificação de tema pelo texto.
5. Workflow: passo no `collect.yml`, como o da Câmara.

### Outras APIs gratuitas de LLM (pesquisadas em 07/11/2026)

Para uma eventual reserva entre o Gemini e o modelo fixo. Cada pedido nosso tem ~10–15 mil tokens de entrada e 2–4 mil de saída; são ~10 pedidos por mês.

| Serviço | Cota gratuita | Serve? |
|---|---|---|
| Gemini (atual) | Flash e Flash-Lite gratuitos; limites por projeto no AI Studio; no plano gratuito o Google pode usar o conteúdo para melhorar os produtos. | Sim. Às vezes responde 503 por sobrecarga (já tratado com nova tentativa e modelo reserva). |
| Cloudflare Workers AI | 10.000 neurons/dia (renova 00:00 UTC) ≈ 310 mil tokens de entrada ou 200 mil de saída por dia no Mistral Small 3.1 24B. Conta Cloudflare e token de API. | Sim, melhor candidato a reserva. |
| OpenRouter (modelos `:free`) | 20 pedidos/min; 50/dia (1.000/dia após comprar 10 créditos alguma vez). Modelos gratuitos mudam com o tempo. | Sim, mas instável. |
| Groq | Plano Developer: 30 pedidos/min, 1.000/dia, 8 mil tokens/min, 200 mil/dia. | Não: um pedido nosso passa de 8 mil tokens. |
| Mistral (Experiment) | Gratuito, sem cartão, limites por organização no console; números oficiais não confirmados. | Provavelmente. |
| GitHub Models | Desativado em 30/07/2026. | Não. |

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
