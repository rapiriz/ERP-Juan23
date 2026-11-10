window.erpAccountSummary = (entries, filters) => {
    const mode = filters.modoFecha || 'todas';
    const from = mode === 'especifica' ? (filters.fecha || '') : (mode === 'periodo' ? (filters.fechaDesde || '') : '');
    const to = mode === 'especifica' ? (filters.fecha || '') : (mode === 'periodo' ? (filters.fechaHasta || '') : '');
    let paid = 0, current = 0, atEnd = 0;
    for (const entry of entries) {
        const amount = Math.round(Number(entry.monto) * 100), date = String(entry.fecha).slice(0, 10);
        current += amount;
        if (!to || date <= to) atEnd += amount;
        if ((!from || date >= from) && (!to || date <= to) &&
            (['pago', 'cobro'].includes(entry.tipo) || String(entry.referencia_externa || '').startsWith('reversion_pago:'))) paid += amount;
    }
    return {pagos_netos: paid / 100, deuda_al_corte: Math.max(0, -atEnd) / 100,
        saldo_a_favor_al_corte: Math.max(0, atEnd) / 100, deuda_actual: Math.max(0, -current) / 100};
};
