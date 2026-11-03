# ItapeNews

Agregador de notícias de Itapetininga-SP. De hora em hora, coleta os veículos da cidade, junta as notícias repetidas e publica um site estático leve, feito para o celular, com previsão do tempo e alertas do INMET.

O site mostra só título, um trecho e a foto de capa. Cada notícia leva ao site original.

## Fontes

| Fonte | Como é coletada | Filtro "Itapetininga" |
|---|---|---|
| g1 Itapetininga e região | RSS | sim |
| Jornal de Itapetininga | RSS (ISO-8859-1) | não |
| Correio de Itapetininga | começo do sitemap (40 KB) + página da notícia | não |
| Prefeitura de Itapetininga | links da home + página da notícia | não |
| Folha de Itapetininga | links da home + página da notícia | não |
| TVS+ (SBT) | RSS da busca do site | sim |
| Câmara Municipal | RSS (Joomla) | não |
| Xeh | RSS | sim |
| Olá Itapetininga | RSS | sim |

Tudo fica em [`config/sources.php`](config/sources.php).

## Como funciona

```
GitHub Actions (de hora em hora, 7h–23h; às 2h e 5h só para os alertas)
  php itape collect   → busca as fontes em paralelo, abre a página só das notícias novas
                        para completar foto/resumo/data, filtra, agrupa e grava data/
  php itape build     → gera build/ (home, arquivo por dia, fontes, feed.xml)
  GitHub Pages        → publica build/
```

- **Sem servidor**: o "backend" é um script de linha de comando (Laravel Zero) que roda e termina.
- **Dados em JSON** no próprio repositório: `data/items/AAAA-MM.json`. O estado das fontes, o tempo e os alertas (`data/state.json`, `weather.json`, `alerts.json`) ficam no cache do Actions e só entram no git junto com notícias novas.
- **Boa vizinhança**: usa ETag quando a fonte oferece, abre cada notícia uma única vez e lê só o começo do sitemap do Correio (o arquivo inteiro tem 7 MB).
- **Uma fonte fora do ar não derruba o site.** O erro aparece em `/fontes/`, e um job diário abre uma issue se alguma fonte quebrar.
- **Agrupamento**: notícias parecidas de fontes diferentes, publicadas em até 48 h, viram um card só, com "Também em".

## Rodando localmente

Requer PHP 8.4+ e Composer.

```bash
composer install
php itape collect          # coleta (grava em data/)
php itape build            # gera o site em build/
php -S localhost:8000 -t build
```

Outros comandos:

```bash
php itape collect --source=g1 --source=jornal   # só algumas fontes
php itape collect --skip-weather                # sem tempo/alertas
php itape check-sources --live                  # testa todas as fontes agora
composer check                                  # Pint + Larastan + Pest
```

## Testes

```bash
composer test
```

Os testes usam cópias reais das fontes em `tests/Fixtures/` e **nunca acessam a internet** (`Http::preventStrayRequests`). Quando uma fonte mudar de formato, salve a nova página em `tests/Fixtures/`, ajuste o adaptador e escreva um teste para o caso.

## Adicionando uma fonte

1. Descubra se o site tem RSS (`/feed`, `/rss`, `?format=feed&type=rss`, `<link rel="alternate">` no HTML).
2. Adicione uma entrada em `config/sources.php`:
   - `type => 'rss'` com a URL do feed; ou
   - `type => 'html_list'` com a página que lista as notícias e uma `link_pattern` (regex) que reconhece os links de notícia. Use `page` para seletores CSS de resumo/data quando as meta tags `og:*` não bastarem.
   - `filter => true` se a fonte publica notícias de outras cidades.
3. Rode `php itape check-sources --live` e `php itape collect --source=nova`.
4. Salve uma cópia em `tests/Fixtures/` e adicione um teste.

## Publicando no GitHub Pages

1. Crie um repositório **público** no GitHub e envie o código (`main`). Em repositório público, os minutos do Actions são ilimitados. Em um privado, o GitHub Pages exige plano pago.
2. Em **Settings → Pages**, escolha **Source: GitHub Actions**.
3. (Opcional) Em **Settings → Secrets and variables → Actions → Variables**, configure:
   - `SITE_NAME`: nome do site (padrão: ItapeNews)
   - `SITE_TAGLINE`: frase de descrição
   - `GOATCOUNTER_CODE`: código do [GoatCounter](https://www.goatcounter.com/) (ex.: `itapenews` para `itapenews.goatcounter.com`)
   - `SITE_CONTACT`: e-mail exibido em `/fontes/` para pedidos de remoção
   - `SITE_DOMAIN`: domínio próprio (ex.: `itapehoje.com.br`). Configure também o DNS e o campo *Custom domain* em Settings → Pages.
4. Rode o workflow **Coletar e publicar** manualmente em **Actions** na primeira vez. Depois ele roda sozinho nos horários agendados e a cada push na `main` (só publica se os testes passarem).

Observações:
- O GitHub pode atrasar ou pular execuções agendadas quando está sobrecarregado. Com o agendamento antigo, a cada 15 min, ele rodava só uma vez a cada ~5 h. Por isso o agendamento atual pede menos execuções (19 por dia), fora da virada da hora. Se o atraso continuar alto, a alternativa é disparar o workflow por um agendador externo (ex.: cron-job.org) pela API do GitHub.
- O GitHub desativa agendamentos de repositórios sem atividade por 60 dias. Os commits de coleta contam como atividade, mas, se o site ficar parado, reative em **Actions**.

## Estrutura

```
app/Commands/      collect, build, check-sources
app/Sources/       adaptadores: RSS, lista HTML, sitemap parcial
app/Pipeline/      coleta, enriquecimento pela página, filtro, agrupamento
app/Weather/       Open-Meteo e avisos do INMET
app/Site/          geração do site estático
resources/views/   templates Blade
resources/assets/  CSS e JS (embutidos nas páginas)
data/              notícias coletadas (JSON)
tests/             Pest + cópias reais das fontes
```
