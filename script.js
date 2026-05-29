// Ejecutar el cálculo inicial apenas cargue la página
document.addEventListener("DOMContentLoaded", () => {
    generarPlanPagos();
});

// Variable global para guardar el estado y reutilizarlo al exportar a Excel
let cachePlanPagos = [];
let variablesGlobales = {};

function generarPlanPagos() {
    // Captura de datos desde el HTML
    const monto = parseFloat(document.getElementById("monto").value) || 0;
    const tasaMensual = (parseFloat(document.getElementById("tasa").value) || 0) / 100;
    const plazoPactado = parseInt(document.getElementById("plazo").value) || 0;
    const valorPrima = parseFloat(document.getElementById("prima").value) || 0;
    const aplicarPrimas = document.getElementById("aplicar_primas").checked;

    // Fórmula PMT / PAGO (Cuota Fija Sistema Francés)
    let cuotaFijaBase = 0;
    if (tasaMensual === 0) {
        cuotaFijaBase = monto / plazoPactado;
    } else {
        cuotaFijaBase = (monto * tasaMensual * Math.pow(1 + tasaMensual, plazoPactado)) / (Math.pow(1 + tasaMensual, plazoPactado) - 1);
    }

    // Inicialización de variables de control
    let saldoInicial = monto;
    let totalCuotas = 0;
    let totalPrimas = 0;
    let totalIntereses = 0;
    let totalCapital = 0;
    let plazoReal = 0;
    
    cachePlanPagos = []; // Reset de caché

    // Ciclo de Amortización
    for (let mes = 1; mes <= plazoPactado; mes++) {
        if (saldoInicial <= 0) break;

        let interesMes = Math.round(saldoInicial * tasaMensual * 100) / 100;
        let primaMes = (aplicarPrimas && (mes % 6 === 0)) ? valorPrima : 0;
        let cuotaMes = cuotaFijaBase;
        let abonoCapital = 0;
        let saldoPendiente = 0;

        // Validación de última cuota para evitar saldos huérfanos
        if ((saldoInicial + interesMes) <= cuotaFijaBase) {
            cuotaMes = saldoInicial + interesMes;
            primaMes = 0;
            abonoCapital = saldoInicial;
            saldoPendiente = 0;
        } else {
            if ((saldoInicial + interesMes - cuotaMes) < primaMes) {
                primaMes = Math.max(0, Math.round((saldoInicial + interesMes - cuotaMes) * 100) / 100);
            }
            abonoCapital = Math.round((cuotaMes - interesMes + primaMes) * 100) / 100;
            saldoPendiente = Math.max(0, Math.round((saldoInicial - abonoCapital) * 100) / 100);
        }

        let avancePct = ((monto - saldoPendiente) / monto) * 100;

        cachePlanPagos.push({
            mes: mes,
            saldo_inicial: saldoInicial,
            cuota: cuotaMes,
            prima: primaMes,
            interes: interesMes,
            abono_capital: abonoCapital,
            saldo_pendiente: saldoPendiente,
            avance: avancePct
        });

        // Sumatorias parciales
        totalCuotas += cuotaMes;
        totalPrimas += primaMes;
        totalIntereses += interesMes;
        totalCapital += abonoCapital;
        plazoReal++;

        saldoInicial = saldoPendiente;
    }

    // Guardar totales globales para Excel
    variablesGlobales = { monto, tasaMensual, plazoReal, totalIntereses, totalCuotas, totalPrimas, totalCapital };

    // Renderizar la información en el DOM (HTML)
    renderizarInterfaz();
}

function formatearMoneda(valor) {
    return '$' + Math.round(valor).toLocaleString('co-CO');
}

function renderizarInterfaz() {
    // 1. Actualizar Tarjetas KPI
    document.getElementById("kpi-cuota").innerText = formatearMoneda(variablesGlobales.totalCuotas / variablesGlobales.plazoReal);
    document.getElementById("kpi-plazo").innerText = `${variablesGlobales.plazoReal} meses`;
    document.getElementById("kpi-intereses").innerText = formatearMoneda(variablesGlobales.totalIntereses);
    document.getElementById("kpi-total").innerText = formatearMoneda(variablesGlobales.totalCuotas + variablesGlobales.totalPrimas);

    // 2. Llenar el cuerpo de la tabla
    const tbody = document.getElementById("tabla-cuerpo");
    tbody.innerHTML = "";

    cachePlanPagos.forEach((p) => {
        const fila = document.createElement("tr");
        fila.className = p.mes % 2 === 0 ? "bg-slate-50/50" : "bg-white";

        fila.innerHTML = `
            <td class="py-1.5 px-1 text-center text-slate-400 font-mono text-[10px]">${p.mes}</td>
            <td class="py-1.5 px-1 text-right text-slate-600">${formatearMoneda(p.saldo_inicial)}</td>
            <td class="py-1.5 px-1 text-right text-slate-900">${formatearMoneda(p.cuota)}</td>
            <td class="py-1.5 px-1 text-right ${p.prima > 0 ? 'bg-emerald-50 text-emerald-700 font-bold' : 'text-slate-400'}">${formatearMoneda(p.prima)}</td>
            <td class="py-1.5 px-1 text-right text-red-500">${formatearMoneda(p.interes)}</td>
            <td class="py-1.5 px-1 text-right text-sky-600">${formatearMoneda(p.abono_capital)}</td>
            <td class="py-1.5 px-1 text-right text-slate-900 font-semibold">${formatearMoneda(p.saldo_pendiente)}</td>
            <td class="py-1.5 px-1 text-center font-mono text-[10px] text-slate-500">
                <span class="inline-block px-1 py-0.2 rounded ${p.avance >= 100 ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100'}">
                    ${p.avance.toFixed(1)}%
                </span>
            </td>
        `;
        tbody.appendChild(fila);
    });

    // 3. Llenar el pie de la tabla con los totales concluyentes
    const tfoot = document.getElementById("tabla-pie");
    tfoot.innerHTML = `
        <td class="py-2 px-1 text-center text-[10px]">TOTAL</td>
        <td class="py-2 px-1 text-right text-slate-400 font-normal">-</td>
        <td class="py-2 px-1 text-right">${formatearMoneda(variablesGlobales.totalCuotas)}</td>
        <td class="py-2 px-1 text-right text-emerald-400">${formatearMoneda(variablesGlobales.totalPrimas)}</td>
        <td class="py-2 px-1 text-right text-red-400">${formatearMoneda(variablesGlobales.totalIntereses)}</td>
        <td class="py-2 px-1 text-right text-sky-400">${formatearMoneda(variablesGlobales.totalCapital)}</td>
        <td class="py-2 px-1 text-right text-slate-400 font-normal">-</td>
        <td class="py-2 px-1 text-center text-slate-400 font-normal">100%</td>
    `;
}

// 3. EXPORTACIÓN NATIVA A EXCEL DESDE EL NAVEGADOR
function exportarExcel() {
    let htmlTemplate = `
    <meta http-equiv="content-type" content="application/vnd.ms-excel; charset=UTF-8">
    <table border="1">
        <tr><th colspan="7" style="background-color: #1F497D; color: white; font-size: 16px; font-weight: bold;">RESUMEN DEL CRÉDITO</th></tr>
        <tr><td><b>Monto Solicitado:</b></td><td>${variablesGlobales.monto}</td></tr>
        <tr><td><b>Tasa Mensual:</b></td><td>${(variablesGlobales.tasaMensual * 100).toFixed(2)}%</td></tr>
        <tr><td><b>Plazo Real:</b></td><td>${variablesGlobales.plazoReal} meses</td></tr>
        <tr><td><b>Total Intereses:</b></td><td>${variablesGlobales.totalIntereses.toFixed(2)}</td></tr>
        <tr><td><b>Total Pagado:</b></td><td>${(variablesGlobales.totalCuotas + variablesGlobales.totalPrimas).toFixed(2)}</td></tr>
    </table>
    <br/>
    <table border="1">
        <thead style="background-color: #1F497D; color: white; font-weight: bold;">
            <tr>
                <th>Mes</th><th>Saldo Inicial</th><th>Cuota Fija</th><th>Abono Extra</th><th>Intereses</th><th>Abono Capital</th><th>Saldo Pendiente</th>
            </tr>
        </thead>
        <tbody>`;

    cachePlanPagos.forEach((p) => {
        htmlTemplate += `
            <tr>
                <td style="text-align: center;">${p.mes}</td>
                <td style="text-align: right;">${p.saldo_inicial.toFixed(2)}</td>
                <td style="text-align: right;">${p.cuota.toFixed(2)}</td>
                <td style="text-align: right; ${p.prima > 0 ? 'background-color: #E2EFDA;' : ''}">${p.prima.toFixed(2)}</td>
                <td style="text-align: right;">${p.interes.toFixed(2)}</td>
                <td style="text-align: right;">${p.abono_capital.toFixed(2)}</td>
                <td style="text-align: right;">${p.saldo_pendiente.toFixed(2)}</td>
            </tr>`;
    });

    htmlTemplate += `
            <tr style="font-weight: bold; background-color: #DCE6F1;">
                <td style="text-align: center;">Total</td><td>-</td>
                <td style="text-align: right;">${variablesGlobales.totalCuotas.toFixed(2)}</td>
                <td style="text-align: right;">${variablesGlobales.totalPrimas.toFixed(2)}</td>
                <td style="text-align: right;">${variablesGlobales.totalIntereses.toFixed(2)}</td>
                <td style="text-align: right;">${variablesGlobales.totalCapital.toFixed(2)}</td>
                <td>-</td>
            </tr>
        </tbody>
    </table>`;

    // Crear un enlace temporal de descarga en el navegador
    const blob = new Blob([htmlTemplate], { type: "application/vnd.ms-excel" });
    const url = URL.createObjectURL(blob);
    const a = document.createElement("a");
    a.href = url;
    a.download = "Plan_Amortizacion_Javascript.xls";
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
}