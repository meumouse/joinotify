# Arte da página do WordPress.org

Esta pasta **não é enviada dentro do plugin**. O `scripts/deploy-svn.mjs` espelha o conteúdo dela
para o diretório `assets/` do SVN — que fica **ao lado** de `trunk/`, fora do pacote que o usuário
instala. É de onde o WordPress.org tira o banner, o ícone e as capturas de tela da página pública.

> Cuidado com a ambiguidade: a pasta `assets/` **dentro** do plugin (`assets/brand/`) contém os
> arquivos de runtime (SVGs da marca) e continua indo dentro do ZIP normalmente. São coisas
> diferentes com o mesmo nome.

## Arquivos esperados

Todos são opcionais — a página funciona sem eles, só fica sem graça.

| Arquivo | Dimensões | Observações |
| --- | --- | --- |
| `banner-772x250.png` | 772 × 250 | Banner padrão, no topo da página. |
| `banner-1544x500.png` | 1544 × 500 | Versão retina do banner. |
| `icon-128x128.png` | 128 × 128 | Ícone na busca e na tela de plugins do admin. |
| `icon-256x256.png` | 256 × 256 | Versão retina do ícone. |
| `screenshot-1.png` | livre | Corresponde ao item 1 de `== Screenshots ==` no `readme.txt`. |
| `screenshot-2.png` | livre | Item 2, e assim por diante. |

Também são aceitos `.jpg` e `.gif`. Para o ícone, um `icon.svg` substitui os dois PNGs.

## Capturas de tela

A numeração é posicional: `screenshot-N.png` é descrito pela N-ésima linha da seção
`== Screenshots ==` do `readme.txt`. Hoje o `readme.txt` declara quatro:

1. The visual workflow builder.
2. The workflow actions library.
3. Message history with delivery status.
4. Settings, with integrations and senders.

Se você adicionar, remover ou reordenar imagens aqui, ajuste a lista do `readme.txt` junto — as
legendas erradas ficam visíveis na página pública.

Use largura de 1280 px ou mais; o diretório reduz para exibir, então imagens pequenas ficam borradas.

## Publicando

```bash
npm run deploy:assets
```

Isso faz um ensaio (nada é publicado). Confirme o diff e publique com:

```bash
node scripts/deploy-svn.mjs --assets-only --commit
```

A página pública leva cerca de 15 minutos para refletir a mudança.
