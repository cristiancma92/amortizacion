<?php
// 1. PROCESAMIENTO DE DATOS Y LÓGICA FINANCIERA (PHP)
$monto = isset($_POST['monto']) ? floatval($_POST['monto']) : 40000000;
$tasa_mensual = isset($_POST['tasa']) ? floatval($_POST['tasa']) / 100 : 2 / 100;
$plazo_pactado = isset($_POST['plazo']) ? intval($_POST['plazo']) : 48;
$valor_prima = isset($_POST['prima']) ? floatval($_POST['prima']) : 600000;
$aplicar_primas = isset($_POST['aplicar_primas']) ? true : false;

// Función para calcular la cuota fija (Fórmula PMT / PAGO)
function calcular_cuota_fija($p, $i, $n)
{
  if ($i == 0) return $p / $n;
  return ($p * $i * pow(1 + $i, $n)) / (pow(1 + $i, $n) - 1);
}

$cuota_fija_base = calcular_cuota_fija($monto, $tasa_mensual, $plazo_pactado);

// Generación del plan de pagos
$plan_pagos = [];
$saldo_inicial = $monto;
$total_cuotas = 0;
$total_primas = 0;
$total_intereses = 0;
$total_capital = 0;
$plazo_real = 0;

for ($mes = 1; $mes <= $plazo_pactado; $mes++) {
  if ($saldo_inicial <= 0) break;

  $interes_mes = round($saldo_inicial * $tasa_mensual, 2);
  $prima_mes = ($aplicar_primas && ($mes % 6 == 0)) ? $valor_prima : 0;

  if (($saldo_inicial + $interes_mes) <= $cuota_fija_base) {
    $cuota_mes = $saldo_inicial + $interes_mes;
    $prima_mes = 0;
    $abono_capital = $saldo_inicial;
    $saldo_pendiente = 0;
  } else {
    $cuota_mes = $cuota_fija_base;
    if (($saldo_inicial + $interes_mes - $cuota_mes) < $prima_mes) {
      $prima_mes = max(0, round($saldo_inicial + $interes_mes - $cuota_mes, 2));
    }
    $abono_capital = round($cuota_mes - $interes_mes + $prima_mes, 2);
    $saldo_pendiente = round($saldo_inicial - $abono_capital, 2);
  }

  $avance_pct = (($monto - $saldo_pendiente) / $monto) * 100;

  $plan_pagos[] = [
    'mes' => $mes,
    'saldo_inicial' => $saldo_inicial,
    'cuota' => $cuota_mes,
    'prima' => $prima_mes,
    'interes' => $interes_mes,
    'abono_capital' => $abono_capital,
    'saldo_pendiente' => $saldo_pendiente,
    'avance' => $avance_pct
  ];

  $total_cuotas += $cuota_mes;
  $total_primas += $prima_mes;
  $total_intereses += $interes_mes;
  $total_capital += $abono_capital;
  $plazo_real++;

  $saldo_inicial = $saldo_pendiente;
}

// 2. DETECTOR DE EXPORTACIÓN A EXCEL
if (isset($_GET['export']) && $_GET['export'] == 'excel') {
  header("Content-Type: application/vnd.ms-excel; charset=utf-8");
  header("Content-Disposition: attachment; filename=Plan_Amortizacion_40M.xls");
  header("Expires: 0");
  header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
  header("Pragma: public");
?>
  <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
  <table border="1">
    <tr>
      <th colspan="7" style="background-color: #1F497D; color: white; font-size: 16px;">RESUMEN DEL CRÉDITO</th>
    </tr>
    <tr>
      <td><b>Monto Solicitado:</b></td>
      <td><?php echo number_format($monto, 2); ?></td>
    </tr>
    <tr>
      <td><b>Tasa Mensual:</b></td>
      <td><?php echo ($tasa_mensual * 100); ?>%</td>
    </tr>
    <tr>
      <td><b>Plazo Real:</b></td>
      <td><?php echo $plazo_real; ?> meses</td>
    </tr>
    <tr>
      <td><b>Total Intereses:</b></td>
      <td><?php echo number_format($total_intereses, 2); ?></td>
    </tr>
    <tr>
      <td><b>Total Pagado:</b></td>
      <td><?php echo number_format($total_cuotas + $total_primas, 2); ?></td>
    </tr>
  </table>
  <br />
  <table border="1">
    <thead style="background-color: #1F497D; color: white;">
      <tr>
        <th>Mes</th>
        <th>Saldo Inicial</th>
        <th>Cuota Fija</th>
        <th>Abono Extra</th>
        <th>Intereses</th>
        <th>Abono Capital</th>
        <th>Saldo Pendiente</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($plan_pagos as $p): ?>
        <tr>
          <td><?php echo $p['mes']; ?></td>
          <td><?php echo round($p['saldo_inicial'], 2); ?></td>
          <td><?php echo round($p['cuota'], 2); ?></td>
          <td style="<?php echo $p['prima'] > 0 ? 'background-color: #E2EFDA;' : ''; ?>"><?php echo round($p['prima'], 2); ?></td>
          <td><?php echo round($p['interes'], 2); ?></td>
          <td><?php echo round($p['abono_capital'], 2); ?></td>
          <td><?php echo round($p['saldo_pendiente'], 2); ?></td>
        </tr>
      <?php endforeach; ?>
      <tr style="font-weight: bold; background-color: #DCE6F1;">
        <td>Total</td>
        <td>-</td>
        <td><?php echo round($total_cuotas, 2); ?></td>
        <td><?php echo round($total_primas, 2); ?></td>
        <td><?php echo round($total_intereses, 2); ?></td>
        <td><?php echo round($total_capital, 2); ?></td>
        <td>-</td>
      </tr>
    </tbody>
  </table>
<?php
  exit;
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Simulador de Crédito Ajustado</title>
  <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
  <style>
    /* Ajustes críticos para la exportación a PDF e Impresión limpia sin desbordes */
    @media print {
      @page {
        size: letter;
        margin: 0.8cm 0.5cm 0.8cm 0.5cm;
        /* Márgenes optimizados para aprovechar el ancho */
      }

      body {
        background: white;
        color: black;
        font-size: 9px;
      }

      .no-print {
        display: none !important;
      }

      .print-full {
        width: 100% !important;
        max-width: 100% !important;
        min-width: 100% !important;
      }

      .print-card {
        border: 1px solid #e2e8f0;
        box-shadow: none !important;
        border-radius: 8px !important;
      }

      table {
        width: 100% !important;
        table-layout: fixed !important;
        font-size: 8.5px !important;
      }

      th,
      td {
        padding: 4px 2px !important;
      }

      /* Reducción estricta de padding en PDF */
    }
  </style>
</head>

<body class="bg-slate-50 text-slate-800 font-sans antialiased min-h-screen pb-12 text-sm">

  <header class="bg-slate-900 text-white py-4 px-4 mb-6 shadow-md no-print">
    <div class="max-w-7xl mx-auto flex flex-col sm:flex-row justify-between items-center gap-4">
      <div>
        <h1 class="text-xl font-bold tracking-tight">Simulador Financiero Pro</h1>
        <p class="text-slate-400 text-xs">Amortización optimizada en ancho para pantallas y PDF</p>
      </div>
      <div class="flex gap-2">
        <a href="?export=excel" class="bg-emerald-600 hover:bg-emerald-700 text-white px-3 py-1.5 rounded-lg text-xs font-semibold transition shadow-sm">
          📊 Exportar Excel
        </a>
        <button onclick="window.print();" class="bg-sky-600 hover:bg-sky-700 text-white px-3 py-1.5 rounded-lg text-xs font-semibold transition shadow-sm cursor-pointer">
          📄 Exportar PDF / Imprimir
        </button>
      </div>
    </div>
  </header>

  <main class="max-w-7xl mx-auto px-4 grid grid-cols-1 lg:grid-cols-3 gap-6 print-full">

    <section class="lg:col-span-1 no-print">
      <div class="bg-white p-5 rounded-xl shadow-xs border border-slate-200 sticky top-4">
        <h2 class="text-base font-bold text-slate-900 mb-3 pb-2 border-b border-slate-100">Variables del Crédito</h2>
        <form method="POST" action="" class="space-y-3.5">
          <div>
            <label class="block text-xs font-semibold text-slate-500 uppercase mb-1">Monto Solicitado (COP)</label>
            <input type="number" name="monto" value="<?php echo $monto; ?>" class="w-full bg-slate-50 border border-slate-300 rounded-lg px-3 py-1.5 text-slate-900 font-medium text-sm focus:outline-sky-500">
          </div>

          <div>
            <label class="block text-xs font-semibold text-slate-500 uppercase mb-1">Tasa de Interés Mensual (%)</label>
            <input type="number" step="0.01" name="tasa" value="<?php echo $tasa_mensual * 100; ?>" class="w-full bg-slate-50 border border-slate-300 rounded-lg px-3 py-1.5 text-slate-900 font-medium text-sm focus:outline-sky-500">
          </div>

          <div>
            <label class="block text-xs font-semibold text-slate-500 uppercase mb-1">Plazo Solicitado (Meses)</label>
            <input type="number" name="plazo" value="<?php echo $plazo_pactado; ?>" class="w-full bg-slate-50 border border-slate-300 rounded-lg px-3 py-1.5 text-slate-900 font-medium text-sm focus:outline-sky-500">
          </div>

          <div class="p-3.5 bg-slate-50 rounded-xl border border-slate-200">
            <div class="flex items-center justify-between mb-2">
              <label for="aplicar_primas" class="text-xs font-bold text-slate-700 cursor-pointer">Inyectar Primas Semestrales</label>
              <input type="checkbox" id="aplicar_primas" name="aplicar_primas" <?php echo $aplicar_primas ? 'checked' : ''; ?> class="w-4 h-4 text-sky-600 border-slate-300 rounded cursor-pointer">
            </div>
            <div>
              <label class="block text-[10px] font-semibold text-slate-400 uppercase mb-1">Valor Extra de la Prima ($)</label>
              <input type="number" name="prima" value="<?php echo $valor_prima; ?>" class="w-full bg-white border border-slate-300 rounded-md px-2.5 py-1 text-slate-900 font-medium text-xs focus:outline-sky-500">
            </div>
          </div>

          <button type="submit" class="w-full bg-slate-900 hover:bg-slate-800 text-white font-bold py-2 px-4 rounded-lg text-sm transition shadow-md cursor-pointer">
            🔄 Recalcular Plan
          </button>
        </form>
      </div>
    </section>

    <section class="lg:col-span-2 space-y-4 print-full">

      <div class="grid grid-cols-2 md:grid-cols-4 gap-3 print-full">
        <div class="bg-white p-3 rounded-lg shadow-xs border border-slate-200 print-card">
          <p class="text-[10px] font-semibold text-slate-400 uppercase">Cuota Base</p>
          <p class="text-sm font-bold text-slate-900 mt-0.5">$<?php echo number_format($cuota_fija_base, 0, ',', '.'); ?></p>
        </div>
        <div class="bg-white p-3 rounded-lg shadow-xs border border-slate-200 print-card">
          <p class="text-[10px] font-semibold text-slate-400 uppercase">Plazo Real</p>
          <p class="text-sm font-bold text-sky-600 mt-0.5"><?php echo $plazo_real; ?> meses</p>
        </div>
        <div class="bg-white p-3 rounded-lg shadow-xs border border-slate-200 print-card">
          <p class="text-[10px] font-semibold text-slate-400 uppercase">Total Intereses</p>
          <p class="text-sm font-bold text-red-600 mt-0.5">$<?php echo number_format($total_intereses, 0, ',', '.'); ?></p>
        </div>
        <div class="bg-white p-3 rounded-lg shadow-xs border border-slate-200 print-card">
          <p class="text-[10px] font-semibold text-slate-400 uppercase">Total Pagado</p>
          <p class="text-sm font-bold text-emerald-600 mt-0.5">$<?php echo number_format($total_cuotas + $total_primas, 0, ',', '.'); ?></p>
        </div>
      </div>

      <div class="bg-white rounded-xl shadow-xs border border-slate-200 overflow-hidden print-card">
        <div class="px-4 py-2.5 bg-slate-900 text-white flex justify-between items-center no-print">
          <h3 class="text-xs font-bold">Tabla de Amortización Dinámica</h3>
          <span class="text-[10px] bg-slate-700 text-slate-300 px-1.5 py-0.5 rounded font-mono">Ancho Ajustado</span>
        </div>

        <div class="w-full">
          <table class="w-full border-collapse table-fixed text-[11px]">
            <thead>
              <tr class="bg-slate-100 text-slate-500 font-bold uppercase border-b border-slate-200 text-[10px]">
                <th class="py-2 px-1 text-center w-[6%]">Mes</th>
                <th class="py-2 px-1 text-right w-[14%]">Saldo Ini.</th>
                <th class="py-2 px-1 text-right w-[13%]">Cuota Fija</th>
                <th class="py-2 px-1 text-right w-[13%]">Extra (Prima)</th>
                <th class="py-2 px-1 text-right w-[12%]">Intereses</th>
                <th class="py-2 px-1 text-right w-[13%]">Abono Cap.</th>
                <th class="py-2 px-1 text-right w-[15%]">Saldo Pend.</th>
                <th class="py-2 px-1 text-center w-[14%]">% Avance</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 font-medium">
              <?php foreach ($plan_pagos as $p): ?>
                <tr class="<?php echo $p['mes'] % 2 == 0 ? 'bg-slate-50/50' : 'bg-white'; ?>">
                  <td class="py-1.5 px-1 text-center text-slate-400 font-mono text-[10px]"><?php echo $p['mes']; ?></td>
                  <td class="py-1.5 px-1 text-right text-slate-600">$<?php echo number_format($p['saldo_inicial'], 0, ',', '.'); ?></td>
                  <td class="py-1.5 px-1 text-right text-slate-900">$<?php echo number_format($p['cuota'], 0, ',', '.'); ?></td>
                  <td class="py-1.5 px-1 text-right <?php echo $p['prima'] > 0 ? 'bg-emerald-50 text-emerald-700 font-bold print:bg-transparent' : 'text-slate-400'; ?>">
                    $<?php echo number_format($p['prima'], 0, ',', '.'); ?>
                  </td>
                  <td class="py-1.5 px-1 text-right text-red-500">$<?php echo number_format($p['interes'], 0, ',', '.'); ?></td>
                  <td class="py-1.5 px-1 text-right text-sky-600">$<?php echo number_format($p['abono_capital'], 0, ',', '.'); ?></td>
                  <td class="py-1.5 px-1 text-right text-slate-900 font-semibold">$<?php echo number_format($p['saldo_pendiente'], 0, ',', '.'); ?></td>
                  <td class="py-1.5 px-1 text-center font-mono text-[10px] text-slate-500">
                    <span class="inline-block px-1 py-0.2 rounded <?php echo $p['avance'] >= 100 ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100'; ?>">
                      <?php echo number_format($p['avance'], 1); ?>%
                    </span>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
            <tfoot>
              <tr class="bg-slate-800 text-white font-bold border-t border-slate-700 text-[11px]">
                <td class="py-2 px-1 text-center text-[10px]">TOTAL</td>
                <td class="py-2 px-1 text-right text-slate-400 font-normal">-</td>
                <td class="py-2 px-1 text-right">$<?php echo number_format($total_cuotas, 0, ',', '.'); ?></td>
                <td class="py-2 px-1 text-right text-emerald-400">$<?php echo number_format($total_primas, 0, ',', '.'); ?></td>
                <td class="py-2 px-1 text-right text-red-400">$<?php echo number_format($total_intereses, 0, ',', '.'); ?></td>
                <td class="py-2 px-1 text-right text-sky-400">$<?php echo number_format($total_capital, 0, ',', '.'); ?></td>
                <td class="py-2 px-1 text-right text-slate-400 font-normal">-</td>
                <td class="py-2 px-1 text-center text-slate-400 font-normal">100%</td>
              </tr>
            </tfoot>
          </table>
        </div>
      </div>
    </section>
  </main>

</body>

</html>