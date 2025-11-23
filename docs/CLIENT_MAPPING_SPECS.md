# Specifiche Mappatura Cliente - Trentino Immobiliare

> Documento generato da TabelleCliente.xlsx
> Contiene le indicazioni del cliente su cosa importare/eliminare e come mappare i dati

---

## Legenda

| categorie              | elenco delle categorie utilizzabili per gli immobili                                                                        |
|:-----------------------|:----------------------------------------------------------------------------------------------------------------------------|
| micro-categorie        | elenco delle micro-categorie utilizzabili per gli immobili:                                                                 |
|                        | sono raggruppate in base alla categoria padre di appartenenza                                                               |
| Caratteristiche        | elenco generale degli attributi aventi un dominio preciso di valori assegnabili.                                            |
|                        | Note :                                                                                                                      |
|                        | - il valore -1 significa "più del valore massimo disponibile". es. (0,1,2,3,-1) : se selezionato -1 si intende "+ di 3".    |
|                        | - il valore -2 (ove disponibile) significa "dato non indicato" e viene usato dove lo zero costituisce valore significativo. |
| Altri dati disponibili | elenco generale degli attributi che possono assumere valori arbitrari  all'interno di un tipo definito (es: numeric)        |

---

## categorie

**Righe**: 27 | **Colonne**: 3

|   categorie_id | descrizione          | micro categoria              |
|---------------:|:---------------------|:-----------------------------|
|              1 | casa singola         | vedi tabella micro-categorie |
|              2 | bifamiliare          | nan                          |
|              3 | trifamiliare         | vedi tabella micro-categorie |
|              4 | casa a schiera       | vedi tabella micro-categorie |
|              5 | monolocale           | nan                          |
|              7 | cantina              | nan                          |
|              8 | garage               | vedi tabella micro-categorie |
|              9 | magazzino            | nan                          |
|             10 | attivita commerciale | vedi tabella micro-categorie |
|             11 | appartamento         | vedi tabella micro-categorie |
|             12 | attico               | nan                          |
|             13 | rustico              | vedi tabella micro-categorie |
|             14 | negozio              | nan                          |
|             15 | quadrifamiliare      | vedi tabella micro-categorie |
|             16 | capannone            | nan                          |
|             17 | ufficio              | nan                          |
|             18 | villa                | vedi tabella micro-categorie |
|             19 | terreno              | vedi tabella micro-categorie |
|             20 | laboratorio          | nan                          |
|             21 | posto auto           | vedi tabella micro-categorie |
|             22 | bed and breakfast    | nan                          |
|             23 | loft                 | nan                          |
|             24 | multiproprietà       | nan                          |
|             25 | agriturismo          | nan                          |
|             26 | palazzo              | nan                          |
|             27 | hotel - albergo      | nan                          |
|             28 | stanze               | vedi tabella micro-categorie |

---

## micro-categorie

**Righe**: 99 | **Colonne**: 6

|   categorie_micro_id |   categorie_id | Categoria            | descrizione                      | Mappare come           | commento   |
|---------------------:|---------------:|:---------------------|:---------------------------------|:-----------------------|:-----------|
|                    1 |             10 | attivita commerciale | alimentari                       | amenities and features | mantenere  |
|                    2 |             10 | attivita commerciale | attività varie                   | nan                    | eliminare  |
|                    3 |             10 | attivita commerciale | autorimesse                      | amenities and features | mantenere  |
|                    4 |             10 | attivita commerciale | bar                              | amenities and features | mantenere  |
|                    5 |             10 | attivita commerciale | centro commerciale               | amenities and features | mantenere  |
|                    6 |             10 | attivita commerciale | edicole                          | amenities and features | mantenere  |
|                    7 |             10 | attivita commerciale | farmacie                         | amenities and features | mantenere  |
|                    8 |             10 | attivita commerciale | ferramenta/casalinghi            | amenities and features | mantenere  |
|                    9 |             10 | attivita commerciale | sale gioco/scommesse             | amenities and features | mantenere  |
|                   10 |             10 | attivita commerciale | gelaterie                        | amenities and features | mantenere  |
|                   11 |             10 | attivita commerciale | palestre                         | amenities and features | mantenere  |
|                   12 |             10 | attivita commerciale | panifici                         | amenities and features | mantenere  |
|                   13 |             10 | attivita commerciale | pasticcerie                      | amenities and features | mantenere  |
|                   14 |             10 | attivita commerciale | parrucchiere uomo/donna          | amenities and features | mantenere  |
|                   15 |             10 | attivita commerciale | pubs e locali serali             | amenities and features | mantenere  |
|                   16 |             10 | attivita commerciale | ristoranti                       | amenities and features | mantenere  |
|                   17 |             10 | attivita commerciale | pizzerie                         | amenities and features | mantenere  |
|                   18 |             10 | attivita commerciale | solarium e centri estetica       | amenities and features | mantenere  |
|                   19 |             10 | attivita commerciale | tabaccherie                      | amenities and features | mantenere  |
|                   20 |             19 | terreno              | terreno agricolo/coltura         | property details       | mantenere  |
|                   21 |             19 | terreno              | terreno boschivo                 | property details       | mantenere  |
|                   22 |             19 | terreno              | terreno edificabile commerciale  | property details       | mantenere  |
|                   23 |             19 | terreno              | terreno edificabile industriale  | property details       | mantenere  |
|                   24 |             19 | terreno              | terreno edificabile residenziale | property details       | mantenere  |
|                   25 |             10 | attivita commerciale | telefonia/informatica            | property details       | mantenere  |
|                   26 |             10 | attivita commerciale | tintorie/lavanderie              | amenities and features | mantenere  |
|                   27 |             10 | attivita commerciale | video noleggi                    | amenities and features | mantenere  |
|                   28 |             10 | attivita commerciale | showroom                         | amenities and features | mantenere  |
|                   29 |             10 | attivita commerciale | abbigliamento                    | amenities and features | mantenere  |
|                   30 |             10 | attivita commerciale | cartoleria/libreria              | amenities and features | mantenere  |
|                   31 |             10 | attivita commerciale | attività in franchising          | nan                    | eliminare  |
|                   32 |             10 | attivita commerciale | fruttivendolo                    | amenities and features | mantenere  |
|                   33 |             10 | attivita commerciale | macelleria                       | amenities and features | mantenere  |
|                   34 |             10 | attivita commerciale | gastronomia                      | amenities and features | mantenere  |
|                   35 |             10 | attivita commerciale | enoteca                          | amenities and features | mantenere  |
|                   36 |             10 | attivita commerciale | negozio di giocattoli            | amenities and features | mantenere  |
|                   37 |             10 | attivita commerciale | articoli sanitari                | amenities and features | mantenere  |
|                   38 |             10 | attivita commerciale | calzature                        | amenities and features | mantenere  |
|                   39 |             10 | attivita commerciale | prodotti per animali             | amenities and features | mantenere  |
|                   40 |             10 | attivita commerciale | tessuti e tende/merceria         | amenities and features | mantenere  |
|                   41 |             10 | attivita commerciale | borse e pelletterie              | amenities and features | mantenere  |
|                   42 |             10 | attivita commerciale | fioreria                         | amenities and features | mantenere  |
|                   43 |             10 | attivita commerciale | oreficeria                       | amenities and features | mantenere  |
|                   44 |             11 | appartamento         | monolocale                       | property details       | mantenere  |
|                   45 |             11 | appartamento         | bilocale                         | property details       | mantenere  |
|                   46 |             11 | appartamento         | trilocale                        | property details       | mantenere  |
|                   47 |             11 | appartamento         | quadrilocale                     | property details       | mantenere  |
|                   48 |             11 | appartamento         | pentalocale                      | property details       | mantenere  |
|                   49 |             11 | appartamento         | più di 5 locali                  | property details       | mantenere  |
|                   50 |             11 | appartamento         | duplex                           | property details       | mantenere  |
|                   51 |             11 | appartamento         | mansarda                         | property details       | mantenere  |
|                   52 |              3 | trifamiliare         | porzione di testa                | nan                    | eliminare  |
|                   53 |              3 | trifamiliare         | porzione centrale                | nan                    | eliminare  |
|                   54 |              4 | casa a schiera       | porzione di testa                | nan                    | eliminare  |
|                   55 |              4 | casa a schiera       | porzione centrale                | nan                    | eliminare  |
|                   56 |             15 | quadrifamiliare      | porzione di testa                | nan                    | eliminare  |
|                   57 |             15 | quadrifamiliare      | porzione centrale                | nan                    | eliminare  |
|                   58 |              8 | garage               | singolo                          | nan                    | eliminare  |
|                   59 |              8 | garage               | doppio                           | nan                    | eliminare  |
|                   60 |              8 | garage               | triplo                           | nan                    | eliminare  |
|                   61 |             21 | posto auto           | singolo                          | property details       | mantenere  |
|                   62 |             21 | posto auto           | doppio                           | property details       | mantenere  |
|                   63 |             21 | posto auto           | triplo                           | property details       | mantenere  |
|                   64 |             21 | posto auto           | silos                            | nan                    | eliminare  |
|                   65 |             13 | rustico              | rustico di campagna              | nan                    | eliminare  |
|                   66 |             13 | rustico              | baita                            | nan                    | eliminare  |
|                   67 |             13 | rustico              | chalet                           | nan                    | eliminare  |
|                   68 |             13 | rustico              | trullo                           | nan                    | eliminare  |
|                   69 |             13 | rustico              | rudere                           | nan                    | eliminare  |
|                   70 |             13 | rustico              | masseria                         | nan                    | eliminare  |
|                   71 |             13 | rustico              | cascina                          | nan                    | eliminare  |
|                   72 |             13 | rustico              | casale                           | nan                    | eliminare  |
|                   73 |             13 | rustico              | castello                         | nan                    | eliminare  |
|                   74 |             28 | stanze               | studenti                         | property details       | mantenere  |
|                   75 |             28 | stanze               | lavoratori                       | property details       | mantenere  |
|                   76 |             28 | stanze               | entrambi                         | nan                    | eliminare  |
|                   77 |             18 | villa                | moderna                          | nan                    | eliminare  |
|                   78 |             18 | villa                | contemporanea                    | nan                    | eliminare  |
|                   79 |             18 | villa                | d'epoca                          | nan                    | eliminare  |
|                   80 |             13 | rustico              | maso                             | nan                    | eliminare  |
|                   81 |             13 | rustico              | tabià                            | nan                    | eliminare  |
|                   82 |             19 | terreno              | lottizzazione                    | nan                    | eliminare  |
|                   83 |             19 | terreno              | completamento                    | nan                    | eliminare  |
|                   84 |             19 | terreno              | perequazione urbana              | nan                    | eliminare  |
|                   85 |             19 | terreno              | insediativa                      | nan                    | eliminare  |
|                   86 |             19 | terreno              | peri urbana                      | nan                    | eliminare  |
|                   87 |             19 | terreno              | artigianale                      | nan                    | eliminare  |
|                   88 |             19 | terreno              | di tutela                        | nan                    | eliminare  |
|                   89 |             19 | terreno              | di rispetto                      | nan                    | eliminare  |
|                   90 |             19 | terreno              | di interesse paesaggistico       | nan                    | eliminare  |
|                   91 |             13 | rustico              | stalla                           | nan                    | eliminare  |
|                   92 |             10 | attivita commerciale | azienda agricola                 | property details       | mantenere  |
|                   93 |             13 | rustico              | casa colonica                    | property details       | mantenere  |
|                   94 |              1 | casa singola         | terratetto                       | property details       | mantenere  |
|                   95 |             18 | villa                | ville venete                     | nan                    | eliminare  |
|                   96 |             10 | attivita commerciale | friggitorie                      | property details       | mantenere  |
|                   97 |             10 | attivita commerciale | rosticcerie                      | property details       | mantenere  |
|                   98 |             19 | terreno              | vigneto                          | nan                    | eliminare  |
|                   99 |             19 | terreno              | seminativo                       | nan                    | eliminare  |

---

## Caratteristiche

**Righe**: 105 | **Colonne**: 6

|   id | descrizione                       | possibili valori                                                                         | Mappare come           | commento   | Note/domande              |
|-----:|:----------------------------------|:-----------------------------------------------------------------------------------------|:-----------------------|:-----------|:--------------------------|
|    1 | bagni                             | 0;1;2;3;-1                                                                               | property details       | mantenere  | nan                       |
|    2 | camere                            | 0;1;2;3;-1                                                                               | property details       | mantenere  | nan                       |
|    3 | cucina                            | 0;1                                                                                      | property details       | mantenere  | nan                       |
|    4 | soggiorno                         | 0;1                                                                                      | property details       | mantenere  | nan                       |
|    5 | garage                            | 0;1                                                                                      | property details       | mantenere  | nan                       |
|    6 | asta                              | 0;1                                                                                      | property details       | mantenere  | nan                       |
|    7 | ripostigli                        | 0;1;2;-1                                                                                 | property details       | mantenere  | nan                       |
|    8 | cantina                           | 0;1                                                                                      | property details       | mantenere  | nan                       |
|    9 | vendita                           | 0;1                                                                                      | property details       | mantenere  | nan                       |
|   10 | affitto                           | 0;1                                                                                      | property details       | mantenere  | nan                       |
|   11 | mansarda                          | 0;1                                                                                      | amenities and features | mantenere  | nan                       |
|   12 | taverna                           | 0;1                                                                                      | amenities and features | mantenere  | nan                       |
|   13 | ascensore                         | 0;1                                                                                      | amenities and features | mantenere  | nan                       |
|   14 | aria condizionata                 | 0;1                                                                                      | amenities and features | mantenere  | nan                       |
|   15 | arredo                            | 0;1                                                                                      | amenities and features | mantenere  | nan                       |
|   16 | riscaldamento autonomo            | 0;1                                                                                      | amenities and features | mantenere  | nan                       |
|   17 | giardino                          | 0;1                                                                                      | amenities and features | mantenere  | nan                       |
|   18 | ingresso indipendente             | 0;1                                                                                      | amenities and features | mantenere  | nan                       |
|   19 | garage doppio                     | 0;1                                                                                      | amenities and features | mantenere  | nan                       |
|   20 | posto auto                        | 0;1                                                                                      | amenities and features | mantenere  | nan                       |
|   21 | riscaldamento a pavimento         | 0;1                                                                                      | amenities and features | mantenere  | nan                       |
|   22 | soggiorno con angolo cottura      | 0;1                                                                                      | amenities and features | mantenere  | nan                       |
|   23 | allarme                           | 0;1                                                                                      | amenities and features | mantenere  | nan                       |
|   24 | terrazzi                          | 0;1;2;3;-1                                                                               | amenities and features | mantenere  | nan                       |
|   25 | poggioli                          | 0;1;2;3;-1                                                                               | amenities and features | mantenere  | nan                       |
|   26 | lavanderia                        | 0;1                                                                                      | amenities and features | mantenere  | nan                       |
|   27 | piano interrato                   | 0;1                                                                                      | property details       | mantenere  | nan                       |
|   28 | piano terra                       | 0;1                                                                                      | property details       | mantenere  | nan                       |
|   29 | primo piano                       | 0;1                                                                                      | property details       | mantenere  | nan                       |
|   30 | piano intermedio                  | 0;1                                                                                      | property details       | mantenere  | nan                       |
|   31 | ultimo piano                      | 0;1                                                                                      | property details       | mantenere  | nan                       |
|   32 | totale piani                      | -2;0;1;2;3;4;5;6;7;8;9;-1                                                                | property details       | mantenere  | nan                       |
|   33 | piano numero                      | -2;0;1;2;3;4;5;6;7;8;9;10;11;12;13;14;15;16;17;18;19;20;21;22;23;24;25;26;27;28;29;30;-1 | amenities and features | mantenere  | nan                       |
|   34 | riscaldamento centralizzato       | 0;1                                                                                      | amenities and features | mantenere  | nan                       |
|   35 | mare                              | 0;1                                                                                      | property details       | mantenere  | nan                       |
|   36 | montagna                          | 0;1                                                                                      | property details       | mantenere  | nan                       |
|   37 | lago                              | 0;1                                                                                      | property details       | mantenere  | nan                       |
|   38 | terme                             | 0;1                                                                                      | amenities and features | mantenere  | nan                       |
|   39 | collina                           | 0;1                                                                                      | property details       | mantenere  | nan                       |
|   40 | campagna                          | 0;1                                                                                      | property details       | mantenere  | nan                       |
|   41 | nuovo                             | 0;1                                                                                      | property details       | mantenere  | nan                       |
|   42 | immobile di prestigio             | 0;1                                                                                      | nan                    | eliminare  | nan                       |
|   43 | giardino_condominiale             | 0;1                                                                                      | property details       | mantenere  | nan                       |
|   44 | soffitta                          | 0;1                                                                                      | amenities and features | mantenere  | nan                       |
|   45 | grezzo                            | 0;1                                                                                      | amenities and features | mantenere  | nan                       |
|   46 | camino                            | 0;1                                                                                      | amenities and features | mantenere  | nan                       |
|   47 | predisposizione_aria_condizionata | 0;1                                                                                      | amenities and features | mantenere  | nan                       |
|   48 | predisposizione_allarme           | 0;1                                                                                      | amenities and features | mantenere  | nan                       |
|   49 | pannelli_solari                   | 0;1                                                                                      | amenities and features | mantenere  | nan                       |
|   50 | pannelli_fotovoltaici             | 0;1                                                                                      | amenities and features | mantenere  | nan                       |
|   51 | impianto_geotermico               | 0;1                                                                                      | amenities and features | mantenere  | nan                       |
|   52 | aree_esterne                      | 0;1                                                                                      | property details       | mantenere  | nan                       |
|   53 | ribalte                           | 0;1                                                                                      | property details       | mantenere  | nan                       |
|   54 | urbanizzato                       | 0;1                                                                                      | property details       | mantenere  | nan                       |
|   55 | classe_energetica                 | vedi tabella specifica                                                                   | property details       | mantenere  | nan                       |
|   56 | posizione                         | vedi tabella specifica                                                                   | property details       | mantenere  | nan                       |
|   57 | stato_manutenzione                | vedi tabella specifica                                                                   | property details       | mantenere  | nan                       |
|   58 | numero_vetrine                    | 0;1;2;3;-1                                                                               | nan                    | eliminare  | nan                       |
|   59 | carro_ponte                       | 0;1                                                                                      | nan                    | eliminare  | nan                       |
|   60 | impianto_anti_incendio            | 0;1                                                                                      | nan                    | eliminare  | nan                       |
|   61 | cabina_elettrica                  | 0;1                                                                                      | nan                    | eliminare  | nan                       |
|   62 | panorama                          | vedi tabella specifica                                                                   | nan                    | eliminare  | nan                       |
|   63 | piano_semi_interrato              | 0;1                                                                                      | property details       | mantenere  | nan                       |
|   64 | piano_rialzato                    | 0;1                                                                                      | property details       | mantenere  | nan                       |
|   65 | numero_locali                     | 0(automatico);1;2;...n...;15                                                             | property details       | mantenere  | nan                       |
|   66 | piscina                           | 0;1                                                                                      | amenities and features | mantenere  | nan                       |
|   67 | porticato                         | 0;1                                                                                      | amenities and features | mantenere  | nan                       |
|   68 | soppalco                          | 0;1                                                                                      | amenities and features | mantenere  | nan                       |
|   69 | sottotetto                        | 0;1                                                                                      | amenities and features | mantenere  | nan                       |
|   70 | chiavi_in_agenzia                 | 0;1                                                                                      | nan                    | eliminare  | nan                       |
|   71 | accesso_disabili                  | 0;1                                                                                      | amenities and features | mantenere  | si parla di hotel giusto? |
|   72 | area_fitness                      | 0;1                                                                                      | amenities and features | mantenere  | nan                       |
|   73 | frigorifero                       | 0;1                                                                                      | amenities and features | mantenere  | nan                       |
|   74 | lavatrice                         | 0;1                                                                                      | amenities and features | mantenere  | nan                       |
|   75 | lavastoviglie                     | 0;1                                                                                      | amenities and features | mantenere  | nan                       |
|   76 | posto_spiaggia                    | 0;1                                                                                      | amenities and features | mantenere  | nan                       |
|   77 | cassaforte                        | 0;1                                                                                      | amenities and features | mantenere  | nan                       |
|   78 | animali_ammessi                   | 0;1                                                                                      | amenities and features | mantenere  | nan                       |
|   79 | televisione                       | 0;1                                                                                      | amenities and features | mantenere  | nan                       |
|   80 | forno                             | 0;1                                                                                      | amenities and features | mantenere  | nan                       |
|   81 | vasca_idromassaggio               | 0;1                                                                                      | amenities and features | mantenere  | nan                       |
|   82 | caldaia_a_condensazione           | 0;1                                                                                      | amenities and features | mantenere  | nan                       |
|   83 | riscaldamento_semi_autonomo       | 0;1                                                                                      | amenities and features | mantenere  | nan                       |
|   84 | riscaldamento_termopompa          | 0;1                                                                                      | amenities and features | mantenere  | nan                       |
|   85 | raffreddamento                    | 0;1                                                                                      | amenities and features | mantenere  | nan                       |
|   86 | cucina_arredata                   | 0;1                                                                                      | amenities and features | mantenere  | nan                       |
|   87 | portineria                        | 0;1                                                                                      | nan                    | eliminare  | nan                       |
|   88 | domotica                          | 0;1                                                                                      | nan                    | eliminare  | nan                       |
|   89 | tapparelle motorizzate            | 0;1                                                                                      | amenities and features | mantenere  | nan                       |
|   90 | porta blindata                    | 0;1                                                                                      | nan                    | eliminare  | nan                       |
|   91 | contacalorie                      | 0;1                                                                                      | nan                    | eliminare  | nan                       |
|   92 | montacarichi                      | 0;1;2;3;-1                                                                               | nan                    | eliminare  | nan                       |
|   93 | banchine di carico                | 0;1;2;3;-1                                                                               | nan                    | eliminare  | nan                       |
|   94 | numero portoni                    | 0;1;2;3;-1                                                                               | nan                    | eliminare  | nan                       |
|   95 | numero accessi carrai             | 0;1;2;3;-1                                                                               | nan                    | eliminare  | nan                       |
|   96 | cartello                          | vedi tabella specifica                                                                   | nan                    | eliminare  | nan                       |
|   97 | saracinesche                      | 0;1;2;3;-1                                                                               | nan                    | eliminare  | nan                       |
|   98 | vasca                             | 0;1                                                                                      | nan                    | eliminare  | nan                       |
|   99 | zanzariere                        | 0;1                                                                                      | nan                    | eliminare  | nan                       |
|  100 | tende da sole                     | vedi tabella specifica                                                                   | nan                    | eliminare  | nan                       |
|  101 | impianto elettrico                | 0;1;2;3                                                                                  | nan                    | eliminare  | nan                       |
|  102 | allacciamento fognatura           | 0;1                                                                                      | nan                    | eliminare  | nan                       |
|  103 | canna fumaria                     | 0;1                                                                                      | amenities and features | mantenere  | nan                       |
|  104 | connettività                      | 0;1;2                                                                                    | nan                    | eliminare  | nan                       |
|  105 | impianto illuminazione            | 0;1                                                                                      | nan                    | eliminare  | nan                       |

---

## Info  55 - Classe energetica

**Righe**: 14 | **Colonne**: 2

|   possibile valore | significato                                              |
|-------------------:|:---------------------------------------------------------|
|                  0 | In fase di definizione                                   |
|                  1 | A+/passivo (solo per vecchie certificazioni energetiche) |
|                 10 | A4 (solo per APE 2015)                                   |
|                 11 | A3 (solo per APE 2015)                                   |
|                 12 | A2 (solo per APE 2015)                                   |
|                 13 | A1 (solo per APE 2015)                                   |
|                  2 | A                                                        |
|                  3 | B                                                        |
|                  4 | C                                                        |
|                  5 | D                                                        |
|                  6 | E                                                        |
|                  7 | F                                                        |
|                  8 | G                                                        |
|                  9 | Non soggetto a Certificazione                            |

---

## Info  56 - Posizione

**Righe**: 10 | **Colonne**: 2

|   possibile valore | significato                  |
|-------------------:|:-----------------------------|
|                  0 | sconosciuto                  |
|                  1 | area industriale/artigianale |
|                  2 | centro commerciale           |
|                  3 | ad angolo                    |
|                  4 | centrale                     |
|                  5 | servita                      |
|                  6 | forte passaggio              |
|                  7 | fronte lago                  |
|                  8 | fronte strada                |
|                  9 | interna                      |

---

## Info  57 - Stato di manutenzion

**Righe**: 10 | **Colonne**: 2

|   possibile valore | significato        |
|-------------------:|:-------------------|
|                  0 | sconosciuto        |
|                  1 | da ristrutturare   |
|                  2 | ristrutturato      |
|                  3 | discreto           |
|                  4 | buono              |
|                  5 | ottimo             |
|                  6 | nuovo              |
|                  7 | impianti da fare   |
|                  8 | impianti da rifare |
|                  9 | impianti a norma   |

---

## Info  62 - Panorama

**Righe**: 9 | **Colonne**: 2

|   possibile valore | significato     |
|-------------------:|:----------------|
|                  0 | non indicato    |
|                  1 | vista mare      |
|                  2 | vista lago      |
|                  3 | vista monti     |
|                  4 | vista aperta    |
|                  5 | vista monumento |
|                  6 | vista giardino  |
|                  7 | fronte mare     |
|                  8 | lato mare       |

---

## Info  96 - Cartello

**Righe**: 4 | **Colonne**: 2

|   possibile valore | significato   |
|-------------------:|:--------------|
|                  0 | no            |
|                  1 | si            |
|                  2 | rimosso       |
|                  3 | da rimuovere  |

---

## Info 100 - Tende da sole

**Righe**: 3 | **Colonne**: 2

|   possibile valore | significato   |
|-------------------:|:--------------|
|                  0 | no            |
|                  1 | si            |
|                  2 | predisposto   |

---

## Info 101 - Impianto elettrico

**Righe**: 4 | **Colonne**: 2

|   possibile valore | significato   |
|-------------------:|:--------------|
|                  0 | non definito  |
|                  1 | da fare       |
|                  2 | a norma       |
|                  3 | da verificare |

---

## Info 104 - Connettività

**Righe**: 3 | **Colonne**: 2

|   possibile valore | significato   |
|-------------------:|:--------------|
|                  0 | nessuna       |
|                  1 | adsl          |
|                  2 | fibra         |

---

## Altri dati disponibili

**Righe**: 26 | **Colonne**: 4

|   id | descrizione                | formato valori   | Colonna1         |
|-----:|:---------------------------|:-----------------|:-----------------|
|    1 | fatturato                  | numeric          | nan              |
|    2 | fee di ingresso            | numeric          | nan              |
|    3 | volumetria                 | numeric          | nan              |
|    4 | mq giardino                | numeric          | property details |
|    5 | mq aree esterne            | numeric          | property details |
|    6 | altezza piano              | numeric          | nan              |
|    7 | kw cabina elettrica        | numeric          | nan              |
|    8 | distanza dal mare          | numeric          | nan              |
|   12 | catasto_destinazione       | text             | nan              |
|   13 | catasto_rendita            | numeric          | nan              |
|   14 | catasto_foglio             | numeric          | nan              |
|   15 | catasto_particella         | numeric          | nan              |
|   16 | catasto_subalterno         | numeric          | nan              |
|   17 | numero chiavi              | numeric          | nan              |
|   18 | mq ufficio                 | numeric          | property details |
|   19 | superficie lotto           | numeric          | nan              |
|   20 | superficie commerciale     | numeric          | property details |
|   21 | superficie utile           | numeric          | nan              |
|   22 | dimensione accesso carraio | numeric          | nan              |
|   23 | lunghezza                  | numeric          | nan              |
|   24 | larghezza                  | numeric          | nan              |
|   25 | altezza                    | numeric          | nan              |
|   26 | potenza impianto elettrico | numeric          | nan              |
|   27 | deposito cauzionale        | numeric          | nan              |
|   28 | fideiussione               | numeric          | nan              |
|   29 | totale piani unità         | numeric          | nan              |

---
