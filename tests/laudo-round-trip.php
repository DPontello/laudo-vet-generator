<?php

declare(strict_types=1);

/**
 * Round-trip do laudo completo (agregador). Monta o laudo inteiro a partir do
 * payload da Clarinha e verifica que:
 *   - cada orgao presente aparece VERBATIM (texto do compositor, sem alteracao);
 *   - o cabecalho traz os dados do paciente;
 *   - a IMPRESSÃO DIAGNÓSTICA e a Observação aparecem;
 *   - o rodape fixo da medica esta presente (disclaimer, local/data, CRMV).
 *
 * O texto de cada orgao ja e validado byte a byte nos testes por orgao; aqui o
 * foco e a montagem (ordem, secoes e rodape). Comparacao tolerante a espacamento
 * entre secoes, estrita no texto de cada orgao (conforme o plano).
 *
 * Uso: php tests/laudo-round-trip.php
 */

require __DIR__ . '/../src/laudo.php';

$falhas = 0;
function checa(string $rotulo, bool $ok, string $detalhe = ''): void
{
    global $falhas;
    if ($ok) { echo "OK  {$rotulo}\n"; return; }
    $falhas++;
    echo "FALHA  {$rotulo}" . ($detalhe !== '' ? "\n  {$detalhe}" : '') . "\n";
}

/* ---- Payload completo da Clarinha ---- */
$payload = [
    'cabecalho' => [
        'paciente'                 => 'Clarinha',
        'especie'                  => 'Canino',
        'raca'                     => '',
        'sexo'                     => 'F',
        'idade'                    => '7 anos',
        'responsavel'              => 'Bruna',
        'veterinario_requisitante' => 'Dra. Jaqueline',
        'data_exame'               => '2026-06-30',
        'data_laudo'               => '2026-07-02',
    ],
    'orgaos' => [
        'bexiga' => [
            'avaliado' => true, 'replecao' => 'discreta',
            'parede' => ['aspecto' => 'normoespessa', 'espessura_cm' => 0.11],
            'conteudo' => ['homogeneo' => false, 'sedimento' => ['presente' => true, 'quantidade' => 'discreta', 'tipo' => 'sedimentos_urinarios']],
            'litiase' => ['cistolito_presente' => false],
        ],
        'rins' => [
            'avaliado' => true, 'simetria' => 'simetricos',
            'medida_esquerdo_cm' => 4.54, 'medida_direito_cm' => 4.34, 'contornos' => 'regulares',
            'ecogenicidade_cortical' => 'hiperecogenicidade_difusa', 'ecogenicidade_grau' => 'discreta',
            'ecotextura' => 'homogenea', 'relacao_corticomedular' => 'mantida',
            'achados' => [['tipo' => 'mineralizacao_diverticular', 'lado' => 'bilateral', 'grau' => 'discreta']],
            'litiase_ausente' => true,
        ],
        'adrenais' => [
            'avaliado' => true,
            'esquerda' => ['visibilizacao' => 'visibilizada', 'aumentada' => true, 'polo_caudal_cm' => 0.74, 'polo_cranial_cm' => 0.69, 'comprimento_cm' => 1.76],
            'direita'  => ['visibilizacao' => 'nao_visibilizada'],
        ],
        'figado' => [
            'avaliado' => true, 'tamanho' => 'usual', 'bordas' => 'afiladas', 'contornos' => 'regulares',
            'ecogenicidade' => 'hiperecogenicidade_difusa', 'ecogenicidade_grau' => 'discreta',
            'ecotextura' => 'homogenea', 'sistema_porta_anatomico' => true,
            'observacoes' => 'Nota-se área ovalada, parcialmente definida, hiperecogênica e grosseira, medindo '
                . 'aproximadamente 1,27 cm x 0,74 cm, em topografia de lobos esquerdos.',
        ],
        'vesicula_biliar' => [
            'avaliado' => true, 'parede_normoespessa' => true, 'espessura_cm' => 0.15, 'conteudo_anecogenico' => true,
            'lama_biliar' => ['presente' => true, 'quantidade' => 'moderada', 'disposicao' => 'suspensao'],
            'litiase_ausente' => true,
        ],
        'baco' => [
            'avaliado' => true, 'tamanho' => 'esplenomegalia_moderada', 'bordas' => 'abauladas', 'contornos' => 'regulares',
            'ecogenicidade' => 'usual', 'ecotextura' => 'homogenea', 'vascularizacao_anatomica' => true,
            'observacoes' => 'Notam-se algumas áreas circulares, pouco definidas, hipoecogênicas, medindo '
                . 'aproximadamente 1,06 cm x 0,96 cm.',
        ],
        'estomago' => [
            'avaliado' => true, 'conteudo' => 'gas', 'parede' => 'espessada',
            'espessura_min_cm' => 0.39, 'espessura_max_cm' => 0.62, 'estratificacao_mantida' => true,
        ],
        'intestinos' => [
            'avaliado' => true, 'estratificacao_mantida' => true, 'parede' => 'normoespessa',
            'medidas' => ['duodeno_min_cm' => 0.45, 'duodeno_max_cm' => 0.48, 'jejuno_min_cm' => 0.31, 'jejuno_max_cm' => 0.42, 'colon_min_cm' => 0.11, 'colon_max_cm' => 0.18],
            'peristaltismo_preservado' => true, 'obstrucao_ausente' => true,
        ],
        'pancreas' => [
            'avaliado' => true, 'visibilizacao' => 'parcialmente_visibilizado', 'lobo' => 'direito',
            'espessura_cm' => 0.76, 'ecogenicidade_usual' => true, 'reatividade_adjacente' => false,
        ],
        'reprodutor' => [
            'utero_ovarios_ausentes' => ['avaliado' => true],
        ],
        'cavidade_abdominal' => [
            'avaliado' => true, 'sem_alteracoes' => true,
            'hernia' => ['presente' => true, 'descontinuidade_cm' => 0.41, 'regiao' => 'umbilical'],
        ],
    ],
    'impressao_diagnostica' => [
        'Discreta quantidade de sedimentos urinários.',
        'Moderada quantidade de lama biliar densa.',
        'Hérnia umbilical.',
        'Considerar a possibilidade de gastrite.',
        'Adrenomegalia esquerda.',
    ],
    'observacoes_finais' => [
        'A repleção gastrointestinal por conteúdo gasoso e consequente formação de artefato de reverberação '
        . 'impedem sua avaliação completa e de seu conteúdo e a visibilização de possíveis corpos sólidos.',
    ],
];

$laudo = montarLaudo($payload);

/* ---- 1. Cada orgao aparece VERBATIM, na ordem correta ---- */
$ordem = [
    'bexiga' => 'composeBexiga', 'rins' => 'composeRins', 'adrenais' => 'composeAdrenais',
    'figado' => 'composeFigado', 'vesicula_biliar' => 'composeVesiculaBiliar', 'baco' => 'composeBaco',
    'estomago' => 'composeEstomago', 'intestinos' => 'composeIntestinos', 'pancreas' => 'composePancreas',
    'reprodutor' => 'composeReprodutor', 'cavidade_abdominal' => 'composeCavidadeAbdominal',
];
$posAnterior = -1;
foreach ($ordem as $chave => $fn) {
    $texto = $fn((array) $payload['orgaos'][$chave]);
    $pos = strpos($laudo, $texto);
    checa("orgao {$chave} verbatim", $pos !== false, "texto do orgao nao encontrado no laudo montado");
    if ($pos !== false) {
        checa("orgao {$chave} na ordem", $pos > $posAnterior, "orgao fora de ordem");
        $posAnterior = $pos;
    }
}

/* ---- 2. Cabecalho ---- */
checa('cabecalho paciente',   strpos($laudo, 'Paciente: Clarinha') !== false);
checa('cabecalho data curta', strpos($laudo, 'Data do exame: 30/06/2026') !== false);
checa('cabecalho sexo/idade', strpos($laudo, 'Sexo: F') !== false && strpos($laudo, 'Idade: 7 anos') !== false);
checa('cabecalho requisitante', strpos($laudo, 'Requisitante: Dra. Jaqueline') !== false);
checa('titulo', strpos($laudo, 'LAUDO DE ULTRASSONOGRAFIA ABDOMINAL') !== false);

/* ---- 3. Impressao diagnostica + observacoes ---- */
checa('impressao header', strpos($laudo, 'IMPRESSÃO DIAGNÓSTICA:') !== false);
checa('impressao linha', strpos($laudo, 'Adrenomegalia esquerda.') !== false);
checa('observacao header', strpos($laudo, 'Observação:') !== false);
checa('observacao linha', strpos($laudo, 'A repleção gastrointestinal por conteúdo gasoso') !== false);

/* ---- 4. Rodape fixo da medica ---- */
checa('rodape disclaimer', strpos($laudo, 'não possui valor diagnóstico absoluto') !== false);
checa('rodape local/data', strpos($laudo, 'Pouso Alegre, 02 de julho de 2026.') !== false);
checa('rodape CRMV', strpos($laudo, 'CRMV-MG 31116') !== false);
checa('rodape identificacao', strpos($laudo, 'ULTRASSONOGRAFIA VETERINÁRIA') !== false);

echo "\n===== LAUDO MONTADO (Clarinha) =====\n{$laudo}\n===== FIM =====\n";

if ($falhas === 0) { echo "\nOK: round-trip do laudo completo verde.\n"; exit(0); }
echo "\nFALHA: {$falhas} verificacao(oes) divergente(s).\n";
exit(1);
