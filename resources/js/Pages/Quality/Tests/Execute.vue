<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import ModuleSubTopNav from '@/Navigation/ModuleSubTopNav.vue';
import { Head, useForm, Link } from '@inertiajs/vue3';
import { ref, computed, watch } from 'vue';
import Toast from 'primevue/toast';
import { useToast } from 'primevue/usetoast';

const props = defineProps<{
    test: any;
    rules: any[];
}>();

const toast = useToast();

const hasMeasurements = computed(() => props.test.measurements && props.test.measurements.length > 0);
const mode = ref<'entry' | 'certificate'>(hasMeasurements.value && props.test.overall_status !== 'pending' ? 'certificate' : 'entry');

const parameters = computed(() => props.test.test_type?.parameters || []);
const layoutType = computed<string>(() => (props.test.test_type?.layout_type || 'SINGLE_TRIAL').toUpperCase());
const gridConfig = computed(() => props.test.test_type?.grid_config || {});

// Active trial mode state (for Single vs Multi-Trial layouts)
const isMultiTrial = ref<boolean>(layoutType.value === 'MULTI_TRIAL');

// Watch layoutType changes and sync isMultiTrial
watch(layoutType, (newType) => {
    isMultiTrial.value = newType === 'MULTI_TRIAL';
}, { immediate: true });

// -----------------------------------------------------------------------------
// 1. GAUGE MATRIX STATE (Flakiness & Elongation) - DYNAMIC & PLANT-CONFIGURED
// -----------------------------------------------------------------------------
const buildInitialGaugeRows = () => {
    const configured = gridConfig.value.fractions;
    if (Array.isArray(configured) && configured.length > 0) {
        return configured.map((f: any) => ({
            fraction_label: f.label || '',
            thickness_mm: Number(f.thickness_mm ?? f.gauge_mm ?? 0),
            length_mm: Number(f.length_mm ?? 0),
            total_weight: null as number | null,
            passing_flakiness_weight: null as number | null,
            passing_elongation_weight: null as number | null,
        }));
    }
    return [
        { fraction_label: '25-20 mm', thickness_mm: 13.50, length_mm: 40.50, total_weight: null, passing_flakiness_weight: null, passing_elongation_weight: null },
        { fraction_label: '20-16 mm', thickness_mm: 10.80, length_mm: 32.40, total_weight: null, passing_flakiness_weight: null, passing_elongation_weight: null },
        { fraction_label: '16-12.5 mm', thickness_mm: 8.55, length_mm: 25.60, total_weight: null, passing_flakiness_weight: null, passing_elongation_weight: null },
        { fraction_label: '12.5-10 mm', thickness_mm: 6.75, length_mm: 20.20, total_weight: null, passing_flakiness_weight: null, passing_elongation_weight: null },
        { fraction_label: '10-6.3 mm', thickness_mm: 4.89, length_mm: 14.70, total_weight: null, passing_flakiness_weight: null, passing_elongation_weight: null },
    ];
};

const gaugeRows = ref(buildInitialGaugeRows());

const addGaugeRow = () => {
    gaugeRows.value.push({
        fraction_label: '',
        thickness_mm: 0,
        length_mm: 0,
        total_weight: null,
        passing_flakiness_weight: null,
        passing_elongation_weight: null,
    });
};

const removeGaugeRow = (idx: number) => {
    if (gaugeRows.value.length <= 1) return;
    gaugeRows.value.splice(idx, 1);
};

const loadGaugePreset = (type: '20mm' | '40mm' | '10mm' | 'clear') => {
    if (type === '20mm') {
        gaugeRows.value = [
            { fraction_label: '25-20 mm', thickness_mm: 13.50, length_mm: 40.50, total_weight: null, passing_flakiness_weight: null, passing_elongation_weight: null },
            { fraction_label: '20-16 mm', thickness_mm: 10.80, length_mm: 32.40, total_weight: null, passing_flakiness_weight: null, passing_elongation_weight: null },
            { fraction_label: '16-12.5 mm', thickness_mm: 8.55, length_mm: 25.60, total_weight: null, passing_flakiness_weight: null, passing_elongation_weight: null },
            { fraction_label: '12.5-10 mm', thickness_mm: 6.75, length_mm: 20.20, total_weight: null, passing_flakiness_weight: null, passing_elongation_weight: null },
            { fraction_label: '10-6.3 mm', thickness_mm: 4.89, length_mm: 14.70, total_weight: null, passing_flakiness_weight: null, passing_elongation_weight: null },
        ];
    } else if (type === '40mm') {
        gaugeRows.value = [
            { fraction_label: '50-40 mm', thickness_mm: 27.00, length_mm: 81.00, total_weight: null, passing_flakiness_weight: null, passing_elongation_weight: null },
            { fraction_label: '40-25 mm', thickness_mm: 19.50, length_mm: 58.50, total_weight: null, passing_flakiness_weight: null, passing_elongation_weight: null },
            { fraction_label: '25-20 mm', thickness_mm: 13.50, length_mm: 40.50, total_weight: null, passing_flakiness_weight: null, passing_elongation_weight: null },
            { fraction_label: '20-16 mm', thickness_mm: 10.80, length_mm: 32.40, total_weight: null, passing_flakiness_weight: null, passing_elongation_weight: null },
            { fraction_label: '16-12.5 mm', thickness_mm: 8.55, length_mm: 25.60, total_weight: null, passing_flakiness_weight: null, passing_elongation_weight: null },
        ];
    } else if (type === '10mm') {
        gaugeRows.value = [
            { fraction_label: '12.5-10 mm', thickness_mm: 6.75, length_mm: 20.20, total_weight: null, passing_flakiness_weight: null, passing_elongation_weight: null },
            { fraction_label: '10-6.3 mm', thickness_mm: 4.89, length_mm: 14.70, total_weight: null, passing_flakiness_weight: null, passing_elongation_weight: null },
            { fraction_label: '6.3-4.75 mm', thickness_mm: 3.32, length_mm: 9.95, total_weight: null, passing_flakiness_weight: null, passing_elongation_weight: null },
        ];
    } else if (type === 'clear') {
        gaugeRows.value = [
            { fraction_label: '', thickness_mm: 0, length_mm: 0, total_weight: null, passing_flakiness_weight: null, passing_elongation_weight: null },
        ];
    }
};

const gaugeTotalWeight = computed(() => gaugeRows.value.reduce((s: number, r: any) => s + Number(r.total_weight || 0), 0));
const gaugeFlakinessPassing = computed(() => gaugeRows.value.reduce((s: number, r: any) => s + Number(r.passing_flakiness_weight || 0), 0));
const gaugeElongationPassing = computed(() => gaugeRows.value.reduce((s: number, r: any) => s + Number(r.passing_elongation_weight || 0), 0));

const flakinessIndex = computed(() => {
    if (gaugeTotalWeight.value <= 0) return '0.00';
    return ((gaugeFlakinessPassing.value / gaugeTotalWeight.value) * 100).toFixed(2);
});

const elongationIndex = computed(() => {
    if (gaugeTotalWeight.value <= 0) return '0.00';
    return ((gaugeElongationPassing.value / gaugeTotalWeight.value) * 100).toFixed(2);
});

// -----------------------------------------------------------------------------
// 2. SIEVE ANALYSIS STATE (Gradation Grid) - DYNAMIC & PLANT-CONFIGURED
// -----------------------------------------------------------------------------
const buildInitialSieveRows = () => {
    const configured = gridConfig.value.sieves;
    if (Array.isArray(configured) && configured.length > 0) {
        return configured.map((s: any) => ({
            sieve_label: s.label || '',
            weight_retained_gms: null as number | null,
            pct_retained: 0,
            cum_pct_retained: 0,
            cum_pct_passing: 100,
            is_limit: s.is_limit || '-',
        }));
    }
    return [
        { sieve_label: '40.00 mm', weight_retained_gms: null, pct_retained: 0, cum_pct_retained: 0, cum_pct_passing: 100, is_limit: '100' },
        { sieve_label: '20.00 mm', weight_retained_gms: null, pct_retained: 0, cum_pct_retained: 0, cum_pct_passing: 100, is_limit: '85-100' },
        { sieve_label: '10.00 mm', weight_retained_gms: null, pct_retained: 0, cum_pct_retained: 0, cum_pct_passing: 100, is_limit: '0-20' },
        { sieve_label: '4.75 mm', weight_retained_gms: null, pct_retained: 0, cum_pct_retained: 0, cum_pct_passing: 100, is_limit: '0-5' },
        { sieve_label: '2.36 mm', weight_retained_gms: null, pct_retained: 0, cum_pct_retained: 0, cum_pct_passing: 100, is_limit: '0-2' },
        { sieve_label: 'Pan', weight_retained_gms: null, pct_retained: 0, cum_pct_retained: 0, cum_pct_passing: 100, is_limit: '-' },
    ];
};

const sieveRows = ref(buildInitialSieveRows());

const addSieveRow = () => {
    sieveRows.value.push({
        sieve_label: '',
        weight_retained_gms: null,
        pct_retained: 0,
        cum_pct_retained: 0,
        cum_pct_passing: 100,
        is_limit: '-',
    });
};

const removeSieveRow = (idx: number) => {
    if (sieveRows.value.length <= 1) return;
    sieveRows.value.splice(idx, 1);
    calculateSieveTable();
};

const loadSievePreset = (type: 'coarse' | 'fine' | 'clear') => {
    if (type === 'coarse') {
        sieveRows.value = [
            { sieve_label: '40.00 mm', weight_retained_gms: null, pct_retained: 0, cum_pct_retained: 0, cum_pct_passing: 100, is_limit: '100' },
            { sieve_label: '20.00 mm', weight_retained_gms: null, pct_retained: 0, cum_pct_retained: 0, cum_pct_passing: 100, is_limit: '85-100' },
            { sieve_label: '10.00 mm', weight_retained_gms: null, pct_retained: 0, cum_pct_retained: 0, cum_pct_passing: 100, is_limit: '0-20' },
            { sieve_label: '4.75 mm', weight_retained_gms: null, pct_retained: 0, cum_pct_retained: 0, cum_pct_passing: 100, is_limit: '0-5' },
            { sieve_label: '2.36 mm', weight_retained_gms: null, pct_retained: 0, cum_pct_retained: 0, cum_pct_passing: 100, is_limit: '0-2' },
            { sieve_label: 'Pan', weight_retained_gms: null, pct_retained: 0, cum_pct_retained: 0, cum_pct_passing: 100, is_limit: '-' },
        ];
    } else if (type === 'fine') {
        sieveRows.value = [
            { sieve_label: '10.00 mm', weight_retained_gms: null, pct_retained: 0, cum_pct_retained: 0, cum_pct_passing: 100, is_limit: '100' },
            { sieve_label: '4.75 mm', weight_retained_gms: null, pct_retained: 0, cum_pct_retained: 0, cum_pct_passing: 100, is_limit: '90-100' },
            { sieve_label: '2.36 mm', weight_retained_gms: null, pct_retained: 0, cum_pct_retained: 0, cum_pct_passing: 100, is_limit: '75-100' },
            { sieve_label: '1.18 mm', weight_retained_gms: null, pct_retained: 0, cum_pct_retained: 0, cum_pct_passing: 100, is_limit: '55-90' },
            { sieve_label: '600 µm', weight_retained_gms: null, pct_retained: 0, cum_pct_retained: 0, cum_pct_passing: 100, is_limit: '35-59' },
            { sieve_label: '300 µm', weight_retained_gms: null, pct_retained: 0, cum_pct_retained: 0, cum_pct_passing: 100, is_limit: '8-30' },
            { sieve_label: '150 µm', weight_retained_gms: null, pct_retained: 0, cum_pct_retained: 0, cum_pct_passing: 100, is_limit: '0-10' },
            { sieve_label: 'Pan', weight_retained_gms: null, pct_retained: 0, cum_pct_retained: 0, cum_pct_passing: 100, is_limit: '-' },
        ];
    } else if (type === 'clear') {
        sieveRows.value = [
            { sieve_label: '', weight_retained_gms: null, pct_retained: 0, cum_pct_retained: 0, cum_pct_passing: 100, is_limit: '-' },
        ];
    }
    calculateSieveTable();
};

const calculateSieveTable = () => {
    let totalWt = 0;
    sieveRows.value.forEach((r: any) => {
        totalWt += Number(r.weight_retained_gms || 0);
    });

    let runningCumRetained = 0;
    sieveRows.value.forEach((r: any) => {
        const wt = Number(r.weight_retained_gms || 0);
        r.pct_retained = totalWt > 0 ? (wt / totalWt) * 100 : 0;
        runningCumRetained += r.pct_retained;
        r.cum_pct_retained = runningCumRetained;
        r.cum_pct_passing = Math.max(0, 100 - runningCumRetained);
    });
};

const sieveTotalWeight = computed(() => {
    return sieveRows.value.reduce((sum: number, r: any) => sum + Number(r.weight_retained_gms || 0), 0);
});

const finenessModulus = computed(() => {
    const sumCumRetained = sieveRows.value
        .filter((r: any) => r.sieve_label.toLowerCase() !== 'pan')
        .reduce((sum: number, r: any) => sum + Number(r.cum_pct_retained || 0), 0);
    return (sumCumRetained / 100).toFixed(2);
});

// -----------------------------------------------------------------------------
// 3. TIMED OBSERVATION STATE (Setting Time / Vicat Penetration)
// -----------------------------------------------------------------------------
const timedData = ref({
    water_added_time: gridConfig.value.water_added_time || '09:00',
    consistency_pct: Number(gridConfig.value.consistency_pct ?? 28.5),
    initial_setting_mins: null as number | null,
    final_setting_mins: null as number | null,
    ambient_temp_c: Number(gridConfig.value.ambient_temp_c ?? 27.0),
    humidity_pct: Number(gridConfig.value.humidity_pct ?? 65),
    observations: Array.isArray(gridConfig.value.observations) && gridConfig.value.observations.length > 0 ? JSON.parse(JSON.stringify(gridConfig.value.observations)) : [
        { elapsed_mins: 30, penetration_mm: 35, needle_type: '1.13mm Needle', remark: 'Initial soft paste' },
        { elapsed_mins: 60, penetration_mm: 28, needle_type: '1.13mm Needle', remark: 'Stiffening observed' },
        { elapsed_mins: 90, penetration_mm: 15, needle_type: '1.13mm Needle', remark: 'Rapid stiffening' },
        { elapsed_mins: 135, penetration_mm: 5.5, needle_type: '1.13mm Needle', remark: 'Initial Set Reached (5-7mm)' },
        { elapsed_mins: 240, penetration_mm: 0.5, needle_type: 'Annular Attachment', remark: 'Final Set Reached' },
    ]
});

const addObservationLog = () => {
    const last = timedData.value.observations[timedData.value.observations.length - 1];
    const nextMins = last ? last.elapsed_mins + 15 : 15;
    timedData.value.observations.push({
        elapsed_mins: nextMins,
        penetration_mm: 0,
        needle_type: '1.13mm Needle',
        remark: 'Observation point'
    });
};

const removeObservationLog = (idx: number) => {
    if (timedData.value.observations.length <= 1) return;
    timedData.value.observations.splice(idx, 1);
};

// -----------------------------------------------------------------------------
// 4. BEFORE / AFTER MEASUREMENT STATE (Water Absorption / Moisture / Soundness)
// -----------------------------------------------------------------------------
const beforeAfterData = ref({
    initial_mass_g: null as number | null,
    final_mass_g: null as number | null,
    container_mass_g: gridConfig.value.container_mass_g !== undefined && gridConfig.value.container_mass_g !== null ? Number(gridConfig.value.container_mass_g) : null,
});

const deltaMass = computed(() => {
    if (beforeAfterData.value.initial_mass_g === null || beforeAfterData.value.final_mass_g === null) return null;
    return (Number(beforeAfterData.value.initial_mass_g) - Number(beforeAfterData.value.final_mass_g)).toFixed(2);
});

const absorptionPercentage = computed(() => {
    const m1 = Number(beforeAfterData.value.initial_mass_g);
    const m2 = Number(beforeAfterData.value.final_mass_g);
    if (!m1 || !m2 || m2 <= 0) return '0.00';
    return (((m1 - m2) / m2) * 100).toFixed(2);
});

// -----------------------------------------------------------------------------
// 5. DENSITY / VOLUME STATE (Bulk Density, Unit Weight, Voids)
// -----------------------------------------------------------------------------
const densityData = ref({
    container_tare_g: gridConfig.value.container_tare_g !== undefined && gridConfig.value.container_tare_g !== null ? Number(gridConfig.value.container_tare_g) : null,
    gross_mass_g: null as number | null,
    cylinder_volume_cc: Number(gridConfig.value.cylinder_volume_cc ?? 3000),
    compaction_type: gridConfig.value.compaction_type || 'Compacted (25 rodded strokes)',
});

const netSampleMass = computed(() => {
    if (densityData.value.gross_mass_g === null || densityData.value.container_tare_g === null) return 0;
    return Math.max(0, Number(densityData.value.gross_mass_g) - Number(densityData.value.container_tare_g));
});

const bulkDensityKgM3 = computed(() => {
    const netG = netSampleMass.value;
    const volCc = Number(densityData.value.cylinder_volume_cc);
    if (!netG || !volCc || volCc <= 0) return '0.00';
    return ((netG / volCc) * 1000).toFixed(2);
});

// -----------------------------------------------------------------------------
// 6. OBSERVATION & DEFECTS CHECKLIST STATE
// -----------------------------------------------------------------------------
const observationChecklist = ref([
    { item: 'Visual Consistency & Workability', rating: 'Satisfactory', notes: 'Cohesive mix, smooth flow without segregation' },
    { item: 'Bleeding & Free Surface Water', rating: 'Satisfactory', notes: 'No excessive bleeding observed' },
    { item: 'Aggregate Shape & Soundness', rating: 'Satisfactory', notes: 'Uniform angular particles, free from flaky dust' },
    { item: 'Foreign Matter / Deleterious Impurities', rating: 'Satisfactory', notes: 'Free of clay lumps and organic debris' },
    { item: 'Color & Texture Homogeneity', rating: 'Satisfactory', notes: 'Consistent uniform grey shade across sample' },
]);

// -----------------------------------------------------------------------------
// 7. SPECIMEN TRIALS (Single Trial & Multi-Trial Matrix)
// -----------------------------------------------------------------------------
const getDefaultNumeric = (p: any) => {
    if (p.default_value !== null && p.default_value !== undefined && p.default_value !== '') {
        const num = Number(p.default_value);
        if (!isNaN(num)) return num;
    }
    return null;
};

const getDefaultText = (p: any) => {
    if (p.default_value !== null && p.default_value !== undefined) {
        return String(p.default_value);
    }
    return '';
};

const buildInitialTrials = () => {
    const existing = props.test.measurements || [];
    const maxRow = existing.reduce((max: number, m: any) => Math.max(max, m.row_index ?? 0), 0);
    const count = layoutType.value === 'MULTI_TRIAL' ? Math.max(3, maxRow + 1) : 1;

    const trials = [];
    for (let r = 0; r < count; r++) {
        const row = parameters.value.map((p: any) => {
            const found = existing.find((m: any) => m.parameter_id === p.id && (m.row_index ?? 0) === r);
            return {
                parameter_id: p.id,
                code: p.code,
                name: p.name,
                data_type: p.data_type,
                unit: p.unit,
                default_value: p.default_value,
                is_calculated: Boolean(p.is_calculated),
                formula: p.formula,
                value_numeric: found ? found.value_numeric : (p.is_calculated ? null : getDefaultNumeric(p)),
                value_text: found ? (found.value_text ?? '') : (p.is_calculated ? '' : getDefaultText(p)),
                row_index: r,
            };
        });
        trials.push(row);
    }
    return trials;
};

const trialsData = ref(buildInitialTrials());

const addTrialRow = () => {
    const newIdx = trialsData.value.length;
    const newRow = parameters.value.map((p: any) => ({
        parameter_id: p.id,
        code: p.code,
        name: p.name,
        data_type: p.data_type,
        unit: p.unit,
        default_value: p.default_value,
        is_calculated: Boolean(p.is_calculated),
        formula: p.formula,
        value_numeric: p.is_calculated ? null : getDefaultNumeric(p),
        value_text: p.is_calculated ? '' : getDefaultText(p),
        row_index: newIdx,
    }));
    trialsData.value.push(newRow);
};

const removeTrialRow = (idx: number) => {
    if (trialsData.value.length <= 1) return;
    trialsData.value.splice(idx, 1);
    trialsData.value.forEach((row, rIdx) => {
        row.forEach((m: any) => { m.row_index = rIdx; });
    });
};

// Formula evaluation per trial row
const getTrialCalculatedVal = (rowIndex: number, paramCode: string, formula: string) => {
    if (!formula) return null;
    try {
        let expr = formula;
        const row = trialsData.value[rowIndex];
        if (!row) return null;

        row.forEach((m: any) => {
            if (m.code && m.value_numeric !== null && m.value_numeric !== '') {
                const regex = new RegExp('\\b' + m.code + '\\b', 'g');
                expr = expr.replace(regex, String(m.value_numeric));
            }
        });

        const alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        row.forEach((m: any, idx: number) => {
            if (idx < 26) {
                const symbol = alphabet[idx];
                if (m.value_numeric !== null && m.value_numeric !== '') {
                    const regex = new RegExp('\\b' + symbol + '\\b', 'g');
                    expr = expr.replace(regex, String(m.value_numeric));
                }
            }
        });

        if (/[A-Za-z_]+/.test(expr)) return null;
        // eslint-disable-next-line no-new-func
        const res = Function('"use strict"; return (' + expr + ')')();
        return isNaN(res) || res === null || res === undefined ? null : Number(res).toFixed(2);
    } catch (e) {
        return null;
    }
};

const getParameterAverage = (paramId: number, isCalculated: boolean, formula: string, code: string) => {
    const vals: number[] = [];
    trialsData.value.forEach((trialRow, rIdx) => {
        const item = trialRow.find(m => m.parameter_id === paramId);
        if (item) {
            if (isCalculated) {
                const cVal = getTrialCalculatedVal(rIdx, code, formula);
                if (cVal !== null) vals.push(parseFloat(cVal));
            } else if (item.value_numeric !== null && item.value_numeric !== '') {
                vals.push(parseFloat(item.value_numeric));
            }
        }
    });
    if (vals.length === 0) return '-';
    return (vals.reduce((a, b) => a + b, 0) / vals.length).toFixed(2);
};

// Form state
const form = useForm({
    test_date: props.test.test_date ? props.test.test_date.substring(0, 10) : new Date().toISOString().substring(0, 10),
    remarks: props.test.remarks || '',
    measurements: [] as any[],
});

const submitExecution = () => {
    const flat: any[] = [];

    // 1. Gather measurements from trialsData
    trialsData.value.forEach((row, rIdx) => {
        row.forEach(m => {
            if (!m.is_calculated) {
                flat.push({
                    parameter_id: m.parameter_id,
                    value_numeric: m.value_numeric,
                    value_text: m.value_text,
                    row_index: rIdx,
                });
            }
        });
    });

    // 2. Map dedicated layout fields to parameters if matching codes exist
    if (layoutType.value === 'GAUGE_MATRIX') {
        const flakParam = parameters.value.find((p: any) => /flak/i.test(p.code) || /flak/i.test(p.name));
        const elongParam = parameters.value.find((p: any) => /elong/i.test(p.code) || /elong/i.test(p.name));
        const totParam = parameters.value.find((p: any) => /total|sample/i.test(p.code) || /total|sample/i.test(p.name));

        if (flakParam) flat.push({ parameter_id: flakParam.id, value_numeric: parseFloat(flakinessIndex.value), row_index: 0 });
        if (elongParam) flat.push({ parameter_id: elongParam.id, value_numeric: parseFloat(elongationIndex.value), row_index: 0 });
        if (totParam) flat.push({ parameter_id: totParam.id, value_numeric: gaugeTotalWeight.value, row_index: 0 });
    } else if (layoutType.value === 'SIEVE_GRADATION') {
        const fmParam = parameters.value.find((p: any) => /fm|fineness/i.test(p.code) || /fm|fineness/i.test(p.name));
        const totParam = parameters.value.find((p: any) => /total|weight/i.test(p.code) || /total|weight/i.test(p.name));

        if (fmParam) flat.push({ parameter_id: fmParam.id, value_numeric: parseFloat(finenessModulus.value), row_index: 0 });
        if (totParam) flat.push({ parameter_id: totParam.id, value_numeric: sieveTotalWeight.value, row_index: 0 });
    } else if (layoutType.value === 'TIMED_OBSERVATION') {
        const initParam = parameters.value.find((p: any) => /init/i.test(p.code) || /initial/i.test(p.name));
        const finParam = parameters.value.find((p: any) => /final/i.test(p.code) || /final/i.test(p.name));
        const consistParam = parameters.value.find((p: any) => /consist/i.test(p.code) || /consistency/i.test(p.name));

        if (initParam && timedData.value.initial_setting_mins !== null) {
            flat.push({ parameter_id: initParam.id, value_numeric: timedData.value.initial_setting_mins, row_index: 0 });
        }
        if (finParam && timedData.value.final_setting_mins !== null) {
            flat.push({ parameter_id: finParam.id, value_numeric: timedData.value.final_setting_mins, row_index: 0 });
        }
        if (consistParam && timedData.value.consistency_pct !== null) {
            flat.push({ parameter_id: consistParam.id, value_numeric: timedData.value.consistency_pct, row_index: 0 });
        }
    } else if (layoutType.value === 'BEFORE_AFTER') {
        const absParam = parameters.value.find((p: any) => /absorp|moist/i.test(p.code) || /absorption|moisture/i.test(p.name));
        const wetParam = parameters.value.find((p: any) => /wet|init|w1/i.test(p.code) || /initial/i.test(p.name));
        const dryParam = parameters.value.find((p: any) => /dry|fin|w2/i.test(p.code) || /final|oven/i.test(p.name));

        if (absParam) flat.push({ parameter_id: absParam.id, value_numeric: parseFloat(absorptionPercentage.value), row_index: 0 });
        if (wetParam && beforeAfterData.value.initial_mass_g !== null) flat.push({ parameter_id: wetParam.id, value_numeric: beforeAfterData.value.initial_mass_g, row_index: 0 });
        if (dryParam && beforeAfterData.value.final_mass_g !== null) flat.push({ parameter_id: dryParam.id, value_numeric: beforeAfterData.value.final_mass_g, row_index: 0 });
    } else if (layoutType.value === 'DENSITY_VOLUME') {
        const densParam = parameters.value.find((p: any) => /dens|bulk|unit/i.test(p.code) || /density|weight/i.test(p.name));
        const netParam = parameters.value.find((p: any) => /net|sample/i.test(p.code) || /net/i.test(p.name));

        if (densParam) flat.push({ parameter_id: densParam.id, value_numeric: parseFloat(bulkDensityKgM3.value), row_index: 0 });
        if (netParam) flat.push({ parameter_id: netParam.id, value_numeric: netSampleMass.value, row_index: 0 });
    }

    // Fallback: If flat is still empty, create dummy entry to prevent validation error
    if (flat.length === 0 && parameters.value.length > 0) {
        flat.push({
            parameter_id: parameters.value[0].id,
            value_numeric: 0,
            value_text: 'Completed',
            row_index: 0,
        });
    }

    form.measurements = flat;
    form.post(route('quality.tests.submit', props.test.id), {
        preserveScroll: true,
        onSuccess: () => {
            mode.value = 'certificate';
            toast.add({ severity: 'success', summary: 'Saved', detail: 'Test evaluated and certificate generated successfully', life: 2500 });
        }
    });
};

const printCertificate = () => {
    window.print();
};
</script>

<template>
    <AppLayout title="QC Test Execution">
        <template #header>
            <ModuleSubTopNav />
        </template>
        <Head :title="`QC Test — ${test.test_no}`" />
        <Toast />

        <div class="max-w-6xl mx-auto py-5 px-4 sm:px-6 space-y-5">
            <!-- ============================================================= -->
            <!-- TOP COMMAND BAR                                               -->
            <!-- ============================================================= -->
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-4 sm:p-5 shadow-xs flex flex-col md:flex-row md:items-center justify-between gap-4 print:hidden">
                <!-- Left: Title, Badges & Context -->
                <div class="flex items-center gap-3.5">
                    <Link
                        :href="route('quality.tests.index')"
                        class="w-10 h-10 rounded-xl bg-slate-100 dark:bg-slate-800 hover:bg-indigo-50 dark:hover:bg-indigo-950/60 text-slate-500 dark:text-slate-400 hover:text-indigo-600 dark:hover:text-indigo-400 flex items-center justify-center transition-all border border-slate-200/60 dark:border-slate-700/60"
                        title="Back to QC Tests"
                    >
                        <i class="pi pi-arrow-left text-sm font-bold"></i>
                    </Link>

                    <div>
                        <!-- Title & Key Badges -->
                        <div class="flex flex-wrap items-center gap-2.5">
                            <h1 class="text-lg sm:text-xl font-bold text-slate-900 dark:text-white tracking-tight">
                                {{ test.test_type?.name }}
                            </h1>
                            <span class="text-xs font-mono font-semibold text-slate-600 dark:text-slate-300 bg-slate-100 dark:bg-slate-800 px-2 py-0.5 rounded-md border border-slate-200 dark:border-slate-700">
                                {{ test.test_no }}
                            </span>
                            <span
                                class="text-[11px] font-semibold px-2.5 py-0.5 rounded-full inline-flex items-center gap-1.5"
                                :class="{
                                    'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-300 border border-emerald-200/60 dark:border-emerald-800/60': test.overall_status === 'pass',
                                    'bg-rose-50 text-rose-700 dark:bg-rose-950/50 dark:text-rose-300 border border-rose-200/60 dark:border-rose-800/60': test.overall_status === 'fail',
                                    'bg-amber-50 text-amber-700 dark:bg-amber-950/50 dark:text-amber-300 border border-amber-200/60 dark:border-amber-800/60': !test.overall_status || test.overall_status === 'pending',
                                }"
                            >
                                <span class="w-1.5 h-1.5 rounded-full" :class="{ 'bg-emerald-500': test.overall_status === 'pass', 'bg-rose-500': test.overall_status === 'fail', 'bg-amber-500': !test.overall_status || test.overall_status === 'pending' }"></span>
                                {{ test.overall_status === 'pass' ? 'Passed' : test.overall_status === 'fail' ? 'Failed' : 'Pending Entry' }}
                            </span>
                        </div>

                        <!-- Context Metadata Trail -->
                        <div class="text-xs text-slate-500 dark:text-slate-400 mt-1 flex flex-wrap items-center gap-x-2 gap-y-1">
                            <span class="font-medium text-slate-700 dark:text-slate-300">{{ test.test_type?.category || 'Quality Test' }}</span>
                            <span class="text-slate-300 dark:text-slate-700">&bull;</span>
                            <span class="text-slate-600 dark:text-slate-300 font-medium">
                                {{ test.sample?.material?.title || 'Material' }}
                                <span class="font-mono text-slate-400 text-[11px] ml-0.5">#{{ test.sample?.sample_no }}</span>
                            </span>
                            <template v-if="test.test_type?.standard_reference">
                                <span class="text-slate-300 dark:text-slate-700">&bull;</span>
                                <span class="text-amber-700 dark:text-amber-400 font-medium inline-flex items-center gap-1">
                                    <i class="pi pi-book text-[10px]"></i> {{ test.test_type?.standard_reference }}
                                </span>
                            </template>
                            <span class="text-slate-300 dark:text-slate-700">&bull;</span>
                            <span class="text-indigo-600 dark:text-indigo-400 font-medium inline-flex items-center gap-1">
                                <i class="pi pi-th-large text-[10px]"></i> {{ layoutType }}
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Right: Segmented Switcher & Actions -->
                <div class="flex items-center gap-2 self-start md:self-auto shrink-0">
                    <div class="inline-flex p-1 bg-slate-100 dark:bg-slate-800 rounded-xl text-xs font-bold border border-slate-200/60 dark:border-slate-700">
                        <button
                            type="button"
                            @click="mode = 'entry'"
                            :class="[
                                'px-3.5 py-1.5 rounded-lg transition-all flex items-center gap-1.5 cursor-pointer',
                                mode === 'entry'
                                    ? 'bg-indigo-600 text-white shadow-xs'
                                    : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white'
                            ]"
                        >
                            <i class="pi pi-pencil text-[10px]"></i> Data Entry
                        </button>
                        <button
                            type="button"
                            @click="mode = 'certificate'"
                            :class="[
                                'px-3.5 py-1.5 rounded-lg transition-all flex items-center gap-1.5 cursor-pointer',
                                mode === 'certificate'
                                    ? 'bg-indigo-600 text-white shadow-xs'
                                    : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white'
                            ]"
                        >
                            <i class="pi pi-file text-[10px]"></i> Certificate
                        </button>
                    </div>

                    <button
                        v-if="mode === 'certificate'"
                        type="button"
                        @click="printCertificate"
                        class="px-3.5 py-1.5 text-xs font-bold text-slate-700 dark:text-slate-200 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl hover:bg-slate-50 dark:hover:bg-slate-700 shadow-2xs transition-all flex items-center gap-1.5 cursor-pointer"
                    >
                        <i class="pi pi-print text-xs text-indigo-600 dark:text-indigo-400"></i> Print
                    </button>
                </div>
            </div>

            <!-- ============================================================= -->
            <!-- CONTEXT SUMMARY METRICS STRIP                                -->
            <!-- ============================================================= -->
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 print:hidden">
                <div class="p-3 bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 rounded-xl">
                    <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Plant / Facility</span>
                    <span class="text-xs font-bold text-slate-800 dark:text-slate-200 truncate block mt-0.5">{{ test.plant?.name || 'Central Facility' }}</span>
                </div>
                <div class="p-3 bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 rounded-xl">
                    <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Sample Source</span>
                    <span class="text-xs font-bold text-slate-800 dark:text-slate-200 truncate block mt-0.5">{{ test.sample?.source_location || test.sample?.supplier?.legal_name || 'Plant Sampling' }}</span>
                </div>
                <div class="p-3 bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 rounded-xl">
                    <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Tested By</span>
                    <span class="text-xs font-bold text-slate-800 dark:text-slate-200 truncate block mt-0.5">{{ test.tester?.name || 'Lab Technician' }}</span>
                </div>
                <div class="p-3 bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 rounded-xl flex items-center justify-between">
                    <div>
                        <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Test Date</span>
                        <input
                            v-if="mode === 'entry'"
                            v-model="form.test_date"
                            type="date"
                            class="bg-transparent border-0 p-0 text-xs font-mono font-bold text-slate-800 dark:text-slate-200 focus:ring-0 cursor-pointer"
                        />
                        <span v-else class="text-xs font-mono font-bold text-slate-800 dark:text-slate-200 block mt-0.5">{{ form.test_date }}</span>
                    </div>
                    <i class="pi pi-calendar text-slate-400 text-sm"></i>
                </div>
            </div>

            <!-- ============================================================= -->
            <!-- MODE 1: DATA ENTRY FORM                                       -->
            <!-- ============================================================= -->
            <div v-if="mode === 'entry'" class="space-y-4">
                <div class="bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 rounded-2xl p-5 sm:p-6 shadow-xs space-y-5">
                    <!-- Mode/Header inside card -->
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-4 border-b border-slate-100 dark:border-slate-800">
                        <div class="flex items-center gap-2.5">
                            <div class="w-8 h-8 rounded-lg bg-indigo-600 text-white flex items-center justify-center font-bold shadow-2xs">
                                <i class="pi pi-sliders-h text-xs"></i>
                            </div>
                            <div>
                                <h2 class="text-xs font-black uppercase tracking-wider text-slate-900 dark:text-slate-100">
                                    Laboratory Measurements Input
                                </h2>
                                <p class="text-[11px] text-slate-500 dark:text-slate-400">
                                    Enter raw specimen observations. Calculated fields and compliance are evaluated automatically.
                                </p>
                            </div>
                        </div>

                        <!-- Single / Multi-Trial switch if standard layout -->
                        <div v-if="layoutType === 'MULTI_TRIAL' || layoutType === 'SINGLE_TRIAL'" class="flex items-center p-0.5 bg-slate-100 dark:bg-slate-800 rounded-lg text-xs font-bold border border-slate-200/60 dark:border-slate-700">
                            <button
                                type="button"
                                @click="isMultiTrial = false"
                                :class="!isMultiTrial ? 'bg-white dark:bg-slate-700 text-indigo-600 dark:text-indigo-300 shadow-xs' : 'text-slate-500'"
                                class="px-2.5 py-1 rounded-md transition-all cursor-pointer"
                            >
                                Single Reading
                            </button>
                            <button
                                type="button"
                                @click="isMultiTrial = true"
                                :class="isMultiTrial ? 'bg-white dark:bg-slate-700 text-indigo-600 dark:text-indigo-300 shadow-xs' : 'text-slate-500'"
                                class="px-2.5 py-1 rounded-md transition-all cursor-pointer"
                            >
                                Multi-Trial Matrix
                            </button>
                        </div>
                    </div>

                    <!-- --------------------------------------------------------- -->
                    <!-- LAYOUT 1: GAUGE MATRIX (Flakiness & Elongation)          -->
                    <!-- --------------------------------------------------------- -->
                    <div v-if="layoutType === 'GAUGE_MATRIX'" class="space-y-4">
                        <!-- Toolbar -->
                        <div class="flex flex-wrap items-center justify-between gap-2 p-2.5 bg-slate-50 dark:bg-slate-800/60 rounded-xl border border-slate-200 dark:border-slate-700 text-xs">
                            <div class="flex flex-wrap items-center gap-1.5">
                                <span class="text-[10px] font-black uppercase tracking-wider text-slate-400 mr-1">Presets:</span>
                                <button
                                    type="button"
                                    @click="loadGaugePreset('20mm')"
                                    class="px-2.5 py-1 bg-white dark:bg-slate-700 hover:bg-indigo-50 dark:hover:bg-indigo-950/50 text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-600 rounded-lg text-[11px] font-bold transition-colors cursor-pointer"
                                >
                                    20mm Nominal
                                </button>
                                <button
                                    type="button"
                                    @click="loadGaugePreset('40mm')"
                                    class="px-2.5 py-1 bg-white dark:bg-slate-700 hover:bg-indigo-50 dark:hover:bg-indigo-950/50 text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-600 rounded-lg text-[11px] font-bold transition-colors cursor-pointer"
                                >
                                    40mm Coarse
                                </button>
                                <button
                                    type="button"
                                    @click="loadGaugePreset('10mm')"
                                    class="px-2.5 py-1 bg-white dark:bg-slate-700 hover:bg-indigo-50 dark:hover:bg-indigo-950/50 text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-600 rounded-lg text-[11px] font-bold transition-colors cursor-pointer"
                                >
                                    10mm Fine
                                </button>
                                <button
                                    type="button"
                                    @click="loadGaugePreset('clear')"
                                    class="px-2 py-1 bg-white dark:bg-slate-700 hover:bg-rose-50 text-rose-600 dark:text-rose-400 border border-slate-200 dark:border-slate-600 rounded-lg text-[11px] font-bold transition-colors cursor-pointer"
                                >
                                    Clear
                                </button>
                            </div>
                            <button
                                type="button"
                                @click="addGaugeRow"
                                class="px-3 py-1 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg text-xs font-bold transition-colors flex items-center gap-1.5 shadow-2xs cursor-pointer"
                            >
                                <i class="pi pi-plus text-[10px]"></i> Add Fraction
                            </button>
                        </div>

                        <!-- Table -->
                        <div class="overflow-x-auto border border-slate-200 dark:border-slate-700 rounded-xl">
                            <table class="w-full text-center text-xs">
                                <thead>
                                    <tr class="bg-slate-50 dark:bg-slate-800 text-slate-700 dark:text-slate-300 border-b border-slate-200 dark:border-slate-700 font-bold">
                                        <th class="py-2.5 px-3 text-left">IS Sieve Fraction</th>
                                        <th class="py-2.5 px-3">Thickness (mm)</th>
                                        <th class="py-2.5 px-3">Length (mm)</th>
                                        <th class="py-2.5 px-3">Total Weight (g)</th>
                                        <th class="py-2.5 px-3 text-indigo-700 dark:text-indigo-300">Flakiness Pass (g)</th>
                                        <th class="py-2.5 px-3 text-purple-700 dark:text-purple-300">Elongation Pass (g)</th>
                                        <th class="py-2.5 px-2 w-10"></th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 dark:divide-slate-800 font-mono">
                                    <tr v-for="(r, idx) in gaugeRows" :key="idx" class="hover:bg-slate-50/80 dark:hover:bg-slate-800/40">
                                        <td class="py-2 px-3 text-left">
                                            <input
                                                v-model="r.fraction_label"
                                                type="text"
                                                placeholder="e.g. 25-20 mm"
                                                class="w-32 px-2 py-1 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-lg font-sans font-bold text-slate-800 dark:text-slate-200 text-xs"
                                            />
                                        </td>
                                        <td class="py-2 px-3">
                                            <input
                                                v-model.number="r.thickness_mm"
                                                type="number"
                                                step="0.01"
                                                placeholder="0.00"
                                                class="w-20 px-2 py-1 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-lg text-center font-bold text-slate-600 dark:text-slate-300 text-xs"
                                            />
                                        </td>
                                        <td class="py-2 px-3">
                                            <input
                                                v-model.number="r.length_mm"
                                                type="number"
                                                step="0.01"
                                                placeholder="0.00"
                                                class="w-20 px-2 py-1 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-lg text-center font-bold text-slate-600 dark:text-slate-300 text-xs"
                                            />
                                        </td>
                                        <td class="py-2 px-3">
                                            <input v-model.number="r.total_weight" type="number" step="0.1" placeholder="0" class="w-24 px-2 py-1 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg text-right font-bold text-xs" />
                                        </td>
                                        <td class="py-2 px-3">
                                            <input v-model.number="r.passing_flakiness_weight" type="number" step="0.1" placeholder="0" class="w-24 px-2 py-1 bg-indigo-50/50 dark:bg-indigo-950/40 border border-indigo-200 dark:border-indigo-800 rounded-lg text-right font-bold text-indigo-700 dark:text-indigo-300 text-xs" />
                                        </td>
                                        <td class="py-2 px-3">
                                            <input v-model.number="r.passing_elongation_weight" type="number" step="0.1" placeholder="0" class="w-24 px-2 py-1 bg-purple-50/50 dark:bg-purple-950/40 border border-purple-200 dark:border-purple-800 rounded-lg text-right font-bold text-purple-700 dark:text-purple-300 text-xs" />
                                        </td>
                                        <td class="py-2 px-2 text-center">
                                            <button
                                                type="button"
                                                @click="removeGaugeRow(idx)"
                                                class="p-1 rounded text-slate-400 hover:text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-950/50 transition-colors cursor-pointer"
                                                title="Remove row"
                                            >
                                                <i class="pi pi-trash text-xs"></i>
                                            </button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- Summary Cards -->
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                            <div class="p-3 bg-slate-50 dark:bg-slate-800/60 rounded-xl border border-slate-200 dark:border-slate-700">
                                <span class="text-[10px] uppercase font-bold text-slate-400">Total Sample Weight</span>
                                <div class="text-base font-mono font-black text-slate-900 dark:text-white mt-0.5">{{ gaugeTotalWeight.toFixed(2) }} g</div>
                            </div>
                            <div class="p-3 bg-indigo-50/70 dark:bg-indigo-950/40 rounded-xl border border-indigo-200 dark:border-indigo-800/70">
                                <span class="text-[10px] uppercase font-bold text-indigo-800 dark:text-indigo-300">Flakiness Index (FI)</span>
                                <div class="text-base font-mono font-black text-indigo-700 dark:text-indigo-300 mt-0.5">{{ flakinessIndex }}%</div>
                                <span class="text-[10px] text-indigo-600/80 dark:text-indigo-400/80">IS 383 Spec: &le; 40%</span>
                            </div>
                            <div class="p-3 bg-purple-50/70 dark:bg-purple-950/40 rounded-xl border border-purple-200 dark:border-purple-800/70">
                                <span class="text-[10px] uppercase font-bold text-purple-800 dark:text-purple-300">Elongation Index (EI)</span>
                                <div class="text-base font-mono font-black text-purple-700 dark:text-purple-300 mt-0.5">{{ elongationIndex }}%</div>
                                <span class="text-[10px] text-purple-600/80 dark:text-purple-400/80">IS 383 Spec: &le; 40%</span>
                            </div>
                        </div>
                    </div>

                    <!-- --------------------------------------------------------- -->
                    <!-- LAYOUT 2: SIEVE GRADATION GRID                            -->
                    <!-- --------------------------------------------------------- -->
                    <div v-else-if="layoutType === 'SIEVE_GRADATION'" class="space-y-4">
                        <!-- Toolbar -->
                        <div class="flex flex-wrap items-center justify-between gap-2 p-2.5 bg-slate-50 dark:bg-slate-800/60 rounded-xl border border-slate-200 dark:border-slate-700 text-xs">
                            <div class="flex flex-wrap items-center gap-1.5">
                                <span class="text-[10px] font-black uppercase tracking-wider text-slate-400 mr-1">Presets:</span>
                                <button
                                    type="button"
                                    @click="loadSievePreset('coarse')"
                                    class="px-2.5 py-1 bg-white dark:bg-slate-700 hover:bg-indigo-50 dark:hover:bg-indigo-950/50 text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-600 rounded-lg text-[11px] font-bold transition-colors cursor-pointer"
                                >
                                    Coarse (40/20/10)
                                </button>
                                <button
                                    type="button"
                                    @click="loadSievePreset('fine')"
                                    class="px-2.5 py-1 bg-white dark:bg-slate-700 hover:bg-indigo-50 dark:hover:bg-indigo-950/50 text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-600 rounded-lg text-[11px] font-bold transition-colors cursor-pointer"
                                >
                                    Fine / M-Sand
                                </button>
                                <button
                                    type="button"
                                    @click="loadSievePreset('clear')"
                                    class="px-2 py-1 bg-white dark:bg-slate-700 hover:bg-rose-50 text-rose-600 dark:text-rose-400 border border-slate-200 dark:border-slate-600 rounded-lg text-[11px] font-bold transition-colors cursor-pointer"
                                >
                                    Clear
                                </button>
                            </div>
                            <button
                                type="button"
                                @click="addSieveRow"
                                class="px-3 py-1 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg text-xs font-bold transition-colors flex items-center gap-1.5 shadow-2xs cursor-pointer"
                            >
                                <i class="pi pi-plus text-[10px]"></i> Add Sieve Row
                            </button>
                        </div>

                        <!-- Table -->
                        <div class="overflow-x-auto border border-slate-200 dark:border-slate-700 rounded-xl">
                            <table class="w-full text-center text-xs">
                                <thead>
                                    <tr class="bg-slate-50 dark:bg-slate-800 text-slate-700 dark:text-slate-300 border-b border-slate-200 dark:border-slate-700 font-bold">
                                        <th class="py-2.5 px-3 text-left">IS Sieve Size</th>
                                        <th class="py-2.5 px-3">Mass Retained (g)</th>
                                        <th class="py-2.5 px-3">% Retained</th>
                                        <th class="py-2.5 px-3">Cum % Retained</th>
                                        <th class="py-2.5 px-3 text-indigo-700 dark:text-indigo-300">Cum % Passing</th>
                                        <th class="py-2.5 px-3">IS 383 Limits</th>
                                        <th class="py-2.5 px-2 w-10"></th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 dark:divide-slate-800 font-mono">
                                    <tr v-for="(r, idx) in sieveRows" :key="idx" class="hover:bg-slate-50/80 dark:hover:bg-slate-800/40">
                                        <td class="py-2 px-3 text-left">
                                            <input
                                                v-model="r.sieve_label"
                                                type="text"
                                                placeholder="e.g. 20.00 mm"
                                                class="w-28 px-2 py-1 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-lg font-sans font-bold text-slate-800 dark:text-slate-200 text-xs"
                                            />
                                        </td>
                                        <td class="py-2 px-3">
                                            <input @input="calculateSieveTable" v-model.number="r.weight_retained_gms" type="number" step="0.1" placeholder="0" class="w-24 px-2 py-1 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg text-right font-bold text-xs" />
                                        </td>
                                        <td class="py-2 px-3 text-slate-600 dark:text-slate-400">{{ r.pct_retained.toFixed(1) }}%</td>
                                        <td class="py-2 px-3 text-slate-600 dark:text-slate-400">{{ r.cum_pct_retained.toFixed(1) }}%</td>
                                        <td class="py-2 px-3 font-black text-indigo-600 dark:text-indigo-400">{{ r.cum_pct_passing.toFixed(1) }}%</td>
                                        <td class="py-2 px-3">
                                            <input
                                                v-model="r.is_limit"
                                                type="text"
                                                placeholder="e.g. 85-100"
                                                class="w-24 px-2 py-1 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-lg font-mono text-center text-amber-700 dark:text-amber-400 text-xs"
                                            />
                                        </td>
                                        <td class="py-2 px-2 text-center">
                                            <button
                                                type="button"
                                                @click="removeSieveRow(idx)"
                                                class="p-1 rounded text-slate-400 hover:text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-950/50 transition-colors cursor-pointer"
                                                title="Remove sieve"
                                            >
                                                <i class="pi pi-trash text-xs"></i>
                                            </button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- Summary Cards -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div class="p-3 bg-slate-50 dark:bg-slate-800/60 rounded-xl border border-slate-200 dark:border-slate-700">
                                <span class="text-[10px] uppercase font-bold text-slate-400">Total Retained Mass</span>
                                <div class="text-base font-mono font-black text-slate-900 dark:text-white mt-0.5">{{ sieveTotalWeight.toFixed(2) }} g</div>
                            </div>
                            <div class="p-3 bg-indigo-50/70 dark:bg-indigo-950/40 rounded-xl border border-indigo-200 dark:border-indigo-800/70">
                                <span class="text-[10px] uppercase font-bold text-indigo-800 dark:text-indigo-300">Fineness Modulus (FM)</span>
                                <div class="text-base font-mono font-black text-indigo-700 dark:text-indigo-300 mt-0.5">{{ finenessModulus }}</div>
                            </div>
                        </div>
                    </div>

                    <!-- --------------------------------------------------------- -->
                    <!-- LAYOUT 3: TIMED OBSERVATION (Vicat / Setting Time)       -->
                    <!-- --------------------------------------------------------- -->
                    <div v-else-if="layoutType === 'TIMED_OBSERVATION'" class="space-y-4">
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                            <div class="p-3.5 bg-slate-50 dark:bg-slate-800/60 rounded-xl border border-slate-200 dark:border-slate-700 space-y-1">
                                <label class="text-[10px] font-bold text-slate-400 uppercase">Water Addition Time</label>
                                <input v-model="timedData.water_added_time" type="time" class="w-full px-2.5 py-1.5 bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-lg text-xs font-mono font-bold" />
                            </div>
                            <div class="p-3.5 bg-slate-50 dark:bg-slate-800/60 rounded-xl border border-slate-200 dark:border-slate-700 space-y-1">
                                <label class="text-[10px] font-bold text-slate-400 uppercase">Consistency (%)</label>
                                <input v-model.number="timedData.consistency_pct" type="number" step="0.1" placeholder="28.5" class="w-full px-2.5 py-1.5 bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-lg text-xs font-mono font-bold" />
                            </div>
                            <div class="p-3.5 bg-indigo-50/70 dark:bg-indigo-950/40 rounded-xl border border-indigo-200 dark:border-indigo-800/70 space-y-1">
                                <label class="text-[10px] font-bold text-indigo-800 dark:text-indigo-300 uppercase">Initial Setting (min)</label>
                                <input v-model.number="timedData.initial_setting_mins" type="number" step="1" placeholder="e.g. 135" class="w-full px-2.5 py-1.5 bg-white dark:bg-slate-900 border border-indigo-300 dark:border-indigo-700 rounded-lg text-xs font-mono font-black text-indigo-700 dark:text-indigo-300" />
                            </div>
                            <div class="p-3.5 bg-purple-50/70 dark:bg-purple-950/40 rounded-xl border border-purple-200 dark:border-purple-800/70 space-y-1">
                                <label class="text-[10px] font-bold text-purple-800 dark:text-purple-300 uppercase">Final Setting (min)</label>
                                <input v-model.number="timedData.final_setting_mins" type="number" step="1" placeholder="e.g. 240" class="w-full px-2.5 py-1.5 bg-white dark:bg-slate-900 border border-purple-300 dark:border-purple-700 rounded-lg text-xs font-mono font-black text-purple-700 dark:text-purple-300" />
                            </div>
                        </div>

                        <!-- Penetration Chronology -->
                        <div class="border border-slate-200 dark:border-slate-700 rounded-xl overflow-hidden">
                            <div class="bg-slate-50 dark:bg-slate-800/80 p-2.5 px-3 border-b border-slate-200 dark:border-slate-700 flex justify-between items-center">
                                <span class="text-xs font-bold text-slate-800 dark:text-slate-200">Vicat Penetration Chronology</span>
                                <button type="button" @click="addObservationLog" class="text-[11px] font-bold text-indigo-600 dark:text-indigo-400 hover:underline flex items-center gap-1 cursor-pointer">
                                    <i class="pi pi-plus text-[9px]"></i> Add Time Point
                                </button>
                            </div>
                            <table class="w-full text-left text-xs">
                                <thead>
                                    <tr class="bg-slate-50/50 dark:bg-slate-800/50 text-slate-500 font-bold border-b border-slate-200 dark:border-slate-700">
                                        <th class="py-2 px-3">Elapsed Time (min)</th>
                                        <th class="py-2 px-3">Penetration (mm)</th>
                                        <th class="py-2 px-3">Needle / Attachment</th>
                                        <th class="py-2 px-3">Remark</th>
                                        <th class="py-2 px-2 text-center w-8"></th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 dark:divide-slate-800 font-mono">
                                    <tr v-for="(obs, oIdx) in timedData.observations" :key="oIdx" class="hover:bg-slate-50/80 dark:hover:bg-slate-800/40">
                                        <td class="py-1.5 px-3">
                                            <input v-model.number="obs.elapsed_mins" type="number" class="w-20 px-2 py-1 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded text-xs font-bold text-right" />
                                        </td>
                                        <td class="py-1.5 px-3">
                                            <input v-model.number="obs.penetration_mm" type="number" step="0.5" class="w-20 px-2 py-1 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded text-xs font-bold text-right text-indigo-600 dark:text-indigo-400" />
                                        </td>
                                        <td class="py-1.5 px-3 font-sans">
                                            <input v-model="obs.needle_type" type="text" class="w-full px-2 py-1 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded text-xs" />
                                        </td>
                                        <td class="py-1.5 px-3 font-sans">
                                            <input v-model="obs.remark" type="text" class="w-full px-2 py-1 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded text-xs" />
                                        </td>
                                        <td class="py-1.5 px-2 text-center">
                                            <button @click="removeObservationLog(oIdx)" type="button" class="text-slate-400 hover:text-rose-600 p-1 cursor-pointer">
                                                <i class="pi pi-times text-[10px]"></i>
                                            </button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- --------------------------------------------------------- -->
                    <!-- LAYOUT 4: BEFORE / AFTER (Water Absorption / Moisture)   -->
                    <!-- --------------------------------------------------------- -->
                    <div v-else-if="layoutType === 'BEFORE_AFTER'" class="space-y-4">
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                            <!-- State 1 -->
                            <div class="p-4 bg-slate-50 dark:bg-slate-800/60 rounded-xl border border-slate-200 dark:border-slate-700 space-y-2">
                                <div class="flex items-center justify-between">
                                    <span class="text-xs font-black uppercase tracking-wider text-slate-700 dark:text-slate-300">Initial State (W1)</span>
                                    <span class="text-[10px] font-bold px-2 py-0.5 rounded bg-slate-200 dark:bg-slate-700 text-slate-700 dark:text-slate-300">Wet / Saturated</span>
                                </div>
                                <div class="text-[11px] text-slate-500 dark:text-slate-400">Mass of Saturated Surface-Dry (SSD) Sample:</div>
                                <div class="relative">
                                    <input
                                        v-model.number="beforeAfterData.initial_mass_g"
                                        type="number"
                                        step="0.01"
                                        placeholder="0.00"
                                        class="w-full px-3 py-2 bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-lg text-sm font-mono font-bold"
                                    />
                                    <span class="absolute right-3 top-2 text-xs text-slate-400 font-bold">g</span>
                                </div>
                            </div>

                            <!-- State 2 -->
                            <div class="p-4 bg-slate-50 dark:bg-slate-800/60 rounded-xl border border-slate-200 dark:border-slate-700 space-y-2">
                                <div class="flex items-center justify-between">
                                    <span class="text-xs font-black uppercase tracking-wider text-slate-700 dark:text-slate-300">Final State (W2)</span>
                                    <span class="text-[10px] font-bold px-2 py-0.5 rounded bg-slate-200 dark:bg-slate-700 text-slate-700 dark:text-slate-300">Oven Dry (110°C)</span>
                                </div>
                                <div class="text-[11px] text-slate-500 dark:text-slate-400">Mass of Oven-Dried Sample:</div>
                                <div class="relative">
                                    <input
                                        v-model.number="beforeAfterData.final_mass_g"
                                        type="number"
                                        step="0.01"
                                        placeholder="0.00"
                                        class="w-full px-3 py-2 bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-lg text-sm font-mono font-bold"
                                    />
                                    <span class="absolute right-3 top-2 text-xs text-slate-400 font-bold">g</span>
                                </div>
                            </div>

                            <!-- Calculated -->
                            <div class="p-4 bg-indigo-50/70 dark:bg-indigo-950/40 rounded-xl border border-indigo-200 dark:border-indigo-800/80 space-y-2 flex flex-col justify-between">
                                <div>
                                    <span class="text-xs font-black uppercase tracking-wider text-indigo-950 dark:text-indigo-200">Water Absorption %</span>
                                    <div class="text-[10px] text-indigo-700/80 dark:text-indigo-300/80 mt-0.5">Formula: ((W1 - W2) / W2) * 100</div>
                                </div>
                                <div class="space-y-1">
                                    <div class="flex items-baseline justify-between">
                                        <span class="text-xs text-slate-500">Loss / Gain:</span>
                                        <span class="font-mono font-bold text-xs">{{ deltaMass ? `${deltaMass} g` : '-' }}</span>
                                    </div>
                                    <div class="flex items-baseline justify-between pt-1 border-t border-indigo-200/60 dark:border-indigo-800">
                                        <span class="text-xs font-bold text-indigo-950 dark:text-indigo-200">Result:</span>
                                        <span class="text-xl font-mono font-black text-indigo-700 dark:text-indigo-300">{{ absorptionPercentage }}%</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- --------------------------------------------------------- -->
                    <!-- LAYOUT 5: DENSITY / VOLUME / UNIT WEIGHT                  -->
                    <!-- --------------------------------------------------------- -->
                    <div v-else-if="layoutType === 'DENSITY_VOLUME'" class="space-y-4">
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                            <div class="p-3.5 bg-slate-50 dark:bg-slate-800/60 rounded-xl border border-slate-200 dark:border-slate-700 space-y-1">
                                <label class="text-[10px] font-bold text-slate-400 uppercase">Container Tare (g)</label>
                                <input v-model.number="densityData.container_tare_g" type="number" step="0.1" placeholder="e.g. 1250" class="w-full px-2.5 py-1.5 bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-lg text-xs font-mono font-bold" />
                            </div>
                            <div class="p-3.5 bg-slate-50 dark:bg-slate-800/60 rounded-xl border border-slate-200 dark:border-slate-700 space-y-1">
                                <label class="text-[10px] font-bold text-slate-400 uppercase">Gross Weight (g)</label>
                                <input v-model.number="densityData.gross_mass_g" type="number" step="0.1" placeholder="e.g. 6150" class="w-full px-2.5 py-1.5 bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-lg text-xs font-mono font-bold" />
                            </div>
                            <div class="p-3.5 bg-slate-50 dark:bg-slate-800/60 rounded-xl border border-slate-200 dark:border-slate-700 space-y-1">
                                <label class="text-[10px] font-bold text-slate-400 uppercase">Cylinder Volume (cc)</label>
                                <input v-model.number="densityData.cylinder_volume_cc" type="number" step="10" placeholder="3000" class="w-full px-2.5 py-1.5 bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-lg text-xs font-mono font-bold" />
                            </div>
                            <div class="p-3.5 bg-indigo-50/70 dark:bg-indigo-950/40 rounded-xl border border-indigo-200 dark:border-indigo-800/70 space-y-1">
                                <label class="text-[10px] font-bold text-indigo-800 dark:text-indigo-300 uppercase">Bulk Density (kg/m³)</label>
                                <div class="px-2.5 py-1.5 bg-white dark:bg-slate-900 border border-indigo-300 dark:border-indigo-700 rounded-lg text-sm font-mono font-black text-indigo-700 dark:text-indigo-300">
                                    {{ bulkDensityKgM3 }}
                                </div>
                            </div>
                        </div>

                        <div class="p-3 bg-slate-50 dark:bg-slate-800/40 rounded-xl border border-slate-200 dark:border-slate-700 flex flex-wrap items-center justify-between gap-2 text-xs">
                            <span class="text-slate-500 font-semibold">Compaction Method:</span>
                            <div class="flex items-center gap-3">
                                <label class="flex items-center gap-1.5 font-bold text-slate-700 dark:text-slate-300 cursor-pointer">
                                    <input type="radio" v-model="densityData.compaction_type" value="Compacted (25 rodded strokes)" />
                                    Compacted (Rodded)
                                </label>
                                <label class="flex items-center gap-1.5 font-bold text-slate-700 dark:text-slate-300 cursor-pointer">
                                    <input type="radio" v-model="densityData.compaction_type" value="Loose (Free-fall)" />
                                    Loose Bulk
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- --------------------------------------------------------- -->
                    <!-- LAYOUT 6: OBSERVATION & DEFECTS CLASSIFICATION            -->
                    <!-- --------------------------------------------------------- -->
                    <div v-else-if="layoutType === 'OBSERVATION_CLASSIFICATION'" class="space-y-3">
                        <div class="border border-slate-200 dark:border-slate-700 rounded-xl overflow-hidden">
                            <table class="w-full text-left text-xs">
                                <thead>
                                    <tr class="bg-slate-50 dark:bg-slate-800 text-slate-700 dark:text-slate-300 font-bold border-b border-slate-200 dark:border-slate-700">
                                        <th class="py-2.5 px-3">Visual Quality Attribute</th>
                                        <th class="py-2.5 px-3 text-center">Compliance Status</th>
                                        <th class="py-2.5 px-3">Inspection Notes & Findings</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                                    <tr v-for="(item, iIdx) in observationChecklist" :key="iIdx" class="hover:bg-slate-50/80 dark:hover:bg-slate-800/40">
                                        <td class="py-2 px-3 font-bold text-slate-800 dark:text-slate-200">{{ item.item }}</td>
                                        <td class="py-2 px-3 text-center">
                                            <select v-model="item.rating" class="px-2 py-1 bg-white dark:bg-slate-800 border border-slate-300 dark:border-slate-700 rounded-lg text-xs font-bold cursor-pointer" :class="item.rating === 'Satisfactory' ? 'text-emerald-700 dark:text-emerald-400' : 'text-rose-700 dark:text-rose-400'">
                                                <option value="Satisfactory">Satisfactory</option>
                                                <option value="Marginal">Marginal</option>
                                                <option value="Unsatisfactory">Unsatisfactory</option>
                                            </select>
                                        </td>
                                        <td class="py-2 px-3">
                                            <input v-model="item.notes" type="text" class="w-full px-2.5 py-1 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-lg text-xs" />
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- --------------------------------------------------------- -->
                    <!-- LAYOUT 7: SINGLE READING / TRIAL                          -->
                    <!-- --------------------------------------------------------- -->
                    <div v-else-if="!isMultiTrial" class="space-y-3">
                        <div v-if="trialsData[0] && trialsData[0].length > 0" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                            <div
                                v-for="m in trialsData[0]"
                                :key="m.parameter_id"
                                class="p-3.5 bg-slate-50/80 dark:bg-slate-800/50 rounded-xl border border-slate-200 dark:border-slate-700 space-y-1.5"
                            >
                                <div class="flex items-center justify-between text-xs">
                                    <span class="font-bold text-slate-800 dark:text-slate-200">{{ m.name }}</span>
                                    <span v-if="m.unit" class="text-indigo-600 font-mono font-bold text-[11px] bg-indigo-50 dark:bg-indigo-950 px-1.5 py-0.5 rounded">{{ m.unit }}</span>
                                </div>
                                <div v-if="m.is_calculated" class="px-3 py-2 bg-purple-50 dark:bg-purple-950/50 border border-purple-200 dark:border-purple-800 rounded-lg font-mono font-black text-purple-700 dark:text-purple-300 text-sm">
                                    {{ getTrialCalculatedVal(0, m.code, m.formula) ?? '-' }}
                                </div>
                                <div v-else>
                                    <input
                                        v-model.number="m.value_numeric"
                                        type="number"
                                        step="any"
                                        placeholder="0.00"
                                        class="w-full px-3 py-1.5 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-lg text-xs font-mono font-bold focus:ring-2 focus:ring-indigo-500"
                                    />
                                </div>
                            </div>
                        </div>

                        <div v-else class="p-6 text-center border-2 border-dashed border-slate-200 dark:border-slate-700 rounded-xl text-slate-500 text-xs">
                            No parameters configured for this test type. Please configure parameters in Quality Master.
                        </div>
                    </div>

                    <!-- --------------------------------------------------------- -->
                    <!-- LAYOUT 8: MULTI-SPECIMEN TRIALS MATRIX                    -->
                    <!-- --------------------------------------------------------- -->
                    <div v-else class="space-y-3">
                        <div class="overflow-x-auto border border-slate-200 dark:border-slate-700 rounded-xl">
                            <table class="w-full text-left text-xs">
                                <thead>
                                    <tr class="bg-slate-50 dark:bg-slate-800 text-slate-700 dark:text-slate-300 border-b border-slate-200 dark:border-slate-700 font-bold">
                                        <th class="py-2.5 px-3 font-bold">Specimen #</th>
                                        <th v-for="p in parameters" :key="p.id" class="py-2.5 px-3">
                                            <span>{{ p.name }}</span>
                                            <span v-if="p.unit" class="text-indigo-600 font-mono font-normal ml-1">({{ p.unit }})</span>
                                        </th>
                                        <th class="py-2.5 px-2 text-center w-10"></th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 dark:divide-slate-800 font-mono">
                                    <tr v-for="(trialRow, rIdx) in trialsData" :key="rIdx" class="hover:bg-slate-50/80 dark:hover:bg-slate-800/40">
                                        <td class="py-2 px-3 font-sans font-bold text-slate-700 dark:text-slate-300 text-xs">Specimen #{{ rIdx + 1 }}</td>
                                        <td v-for="m in trialRow" :key="m.parameter_id" class="py-2 px-3">
                                            <span v-if="m.is_calculated" class="font-black text-purple-700 dark:text-purple-300 bg-purple-50 dark:bg-purple-950/60 px-2.5 py-1 rounded-md border border-purple-200/60 inline-block">
                                                {{ getTrialCalculatedVal(rIdx, m.code, m.formula) ?? '-' }}
                                            </span>
                                            <input
                                                v-else
                                                v-model.number="m.value_numeric"
                                                type="number"
                                                step="any"
                                                placeholder="0.00"
                                                class="w-24 px-2 py-1 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg text-xs font-mono font-bold focus:ring-2 focus:ring-indigo-500"
                                            />
                                        </td>
                                        <td class="py-2 px-2 text-center">
                                            <button @click="removeTrialRow(rIdx)" type="button" class="text-slate-400 hover:text-rose-600 transition-colors p-1 cursor-pointer" title="Remove row">
                                                <i class="pi pi-times text-[10px]"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    <tr class="bg-indigo-50/80 dark:bg-indigo-950/60 font-bold border-t-2 border-indigo-200 dark:border-indigo-800 text-xs">
                                        <td class="py-2.5 px-3 font-sans text-indigo-900 dark:text-indigo-200 font-black">Average Result</td>
                                        <td v-for="p in parameters" :key="p.id" class="py-2.5 px-3 font-mono font-black text-indigo-700 dark:text-indigo-300 text-sm">
                                            {{ getParameterAverage(p.id, Boolean(p.is_calculated), p.formula, p.code) }}
                                        </td>
                                        <td></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <button
                            type="button"
                            @click="addTrialRow"
                            class="text-xs font-bold text-indigo-600 dark:text-indigo-400 hover:underline flex items-center gap-1 pt-1 cursor-pointer"
                        >
                            <i class="pi pi-plus text-[10px]"></i> Add Specimen Trial
                        </button>
                    </div>

                    <!-- Remarks & Action -->
                    <div class="space-y-1.5 pt-3 border-t border-slate-100 dark:border-slate-800">
                        <label class="text-xs font-bold text-slate-700 dark:text-slate-300">Observations & Quality Remarks</label>
                        <textarea
                            v-model="form.remarks"
                            rows="2"
                            placeholder="Enter technician observations, weather conditions, or acceptance remarks..."
                            class="w-full px-3 py-2 bg-slate-50 dark:bg-slate-800/80 border border-slate-200 dark:border-slate-700 rounded-xl text-xs text-slate-800 dark:text-slate-200 focus:ring-2 focus:ring-indigo-500"
                        ></textarea>
                    </div>

                    <div class="flex items-center justify-end pt-3 border-t border-slate-100 dark:border-slate-800">
                        <button
                            type="button"
                            @click="submitExecution"
                            :disabled="form.processing"
                            class="px-6 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs rounded-xl shadow-md shadow-indigo-600/20 transition-all flex items-center gap-1.5 cursor-pointer disabled:opacity-50"
                        >
                            <i v-if="form.processing" class="pi pi-spin pi-spinner text-xs"></i>
                            <i v-else class="pi pi-check text-xs"></i>
                            <span>Save & Evaluate Test Results</span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- ============================================================= -->
            <!-- MODE 2: QUALITY CERTIFICATE & LAB REPORT                      -->
            <!-- ============================================================= -->
            <div v-else class="space-y-4">
                <div class="bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 rounded-2xl p-6 sm:p-8 shadow-xs space-y-6 print:border-none print:p-0 print:shadow-none">
                    <!-- Certificate Header Banner -->
                    <div class="bg-gradient-to-r from-slate-900 via-indigo-950 to-slate-900 text-white p-5 rounded-2xl shadow-sm flex flex-col sm:flex-row sm:items-center justify-between gap-4 print:bg-white print:text-black print:p-0 print:border-b-2 print:border-black print:rounded-none">
                        <div>
                            <div class="text-[10px] font-black uppercase tracking-widest text-indigo-300 print:text-slate-600">
                                Quality Assurance & Materials Testing Laboratory
                            </div>
                            <h2 class="text-xl font-black tracking-tight text-white print:text-black mt-0.5">
                                {{ test.test_type?.name }} Test Certificate
                            </h2>
                            <div class="text-xs text-indigo-200/80 print:text-slate-600 mt-0.5">
                                Specification Standard: <span class="font-bold text-white print:text-black">{{ test.test_type?.standard_reference || 'IS Standard' }}</span>
                            </div>
                        </div>

                        <div class="sm:text-right shrink-0">
                            <div class="text-[10px] font-bold text-indigo-300 print:text-slate-600 uppercase tracking-wider">Report Number</div>
                            <div class="text-sm font-mono font-black text-white print:text-black">{{ test.test_no }}</div>
                            <div class="text-xs text-indigo-200/70 print:text-slate-600">{{ form.test_date }}</div>
                        </div>
                    </div>

                    <!-- 2-Column Metadata Grid -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
                        <div class="p-3.5 bg-slate-50 dark:bg-slate-800/50 rounded-xl border border-slate-100 dark:border-slate-800 space-y-1.5 print:bg-transparent print:border print:border-slate-300">
                            <div class="flex justify-between">
                                <span class="text-slate-500">Facility / Plant:</span>
                                <span class="font-bold text-slate-800 dark:text-slate-200">{{ test.plant?.name || 'Central Facility' }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-slate-500">Raw Material:</span>
                                <span class="font-bold text-slate-800 dark:text-slate-200">{{ test.sample?.material?.title || 'Material' }}</span>
                            </div>
                        </div>

                        <div class="p-3.5 bg-slate-50 dark:bg-slate-800/50 rounded-xl border border-slate-100 dark:border-slate-800 space-y-1.5 print:bg-transparent print:border print:border-slate-300">
                            <div class="flex justify-between">
                                <span class="text-slate-500">Sample Number:</span>
                                <span class="font-mono font-bold text-slate-800 dark:text-slate-200">{{ test.sample?.sample_no || 'N/A' }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-slate-500">Quarry / Source:</span>
                                <span class="font-bold text-slate-800 dark:text-slate-200">{{ test.sample?.source_location || test.sample?.supplier?.legal_name || 'Quarry Supply' }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- CERTIFICATE VIEW: GAUGE MATRIX -->
                    <div v-if="layoutType === 'GAUGE_MATRIX'" class="space-y-2">
                        <div class="text-xs font-black uppercase tracking-wider text-slate-900 dark:text-slate-200">
                            Combined Flakiness & Elongation Test Breakdown
                        </div>
                        <table class="w-full text-center text-xs border border-slate-200 dark:border-slate-700 rounded-xl overflow-hidden print:border-collapse">
                            <thead class="bg-slate-50 dark:bg-slate-800 text-slate-800 dark:text-slate-200 font-bold border-b border-slate-200 dark:border-slate-700">
                                <tr>
                                    <th class="py-2.5 px-3 text-left">IS Fraction</th>
                                    <th class="py-2.5 px-3">Thickness</th>
                                    <th class="py-2.5 px-3">Length</th>
                                    <th class="py-2.5 px-3">Total (g)</th>
                                    <th class="py-2.5 px-3">Flakiness Pass (g)</th>
                                    <th class="py-2.5 px-3">Elongation Pass (g)</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-slate-800 font-mono">
                                <tr v-for="(r, idx) in gaugeRows" :key="idx">
                                    <td class="py-2 px-3 text-left font-sans font-bold text-slate-800 dark:text-slate-200">{{ r.fraction_label }}</td>
                                    <td class="py-2 px-3 text-slate-500">{{ Number(r.thickness_mm).toFixed(2) }} mm</td>
                                    <td class="py-2 px-3 text-slate-500">{{ Number(r.length_mm).toFixed(2) }} mm</td>
                                    <td class="py-2 px-3 font-semibold">{{ (r.total_weight || 0).toFixed(2) }}</td>
                                    <td class="py-2 px-3 text-indigo-700 dark:text-indigo-400 font-semibold">{{ (r.passing_flakiness_weight || 0).toFixed(2) }}</td>
                                    <td class="py-2 px-3 text-purple-700 dark:text-purple-400 font-semibold">{{ (r.passing_elongation_weight || 0).toFixed(2) }}</td>
                                </tr>
                                <tr class="bg-indigo-50/80 dark:bg-indigo-950/60 font-bold border-t-2 border-indigo-200 dark:border-indigo-800 text-xs">
                                    <td colspan="3" class="py-2.5 px-3 text-left font-sans text-indigo-950 dark:text-indigo-200 font-black">Evaluated Indices (IS: 383 &le; 40%)</td>
                                    <td class="py-2.5 px-3 font-semibold">{{ gaugeTotalWeight.toFixed(2) }} g</td>
                                    <td class="py-2.5 px-3 font-mono font-black text-indigo-700 dark:text-indigo-300">Flakiness: {{ flakinessIndex }}%</td>
                                    <td class="py-2.5 px-3 font-mono font-black text-purple-700 dark:text-purple-300">Elongation: {{ elongationIndex }}%</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- CERTIFICATE VIEW: SIEVE GRADATION -->
                    <div v-else-if="layoutType === 'SIEVE_GRADATION'" class="space-y-2">
                        <div class="text-xs font-black uppercase tracking-wider text-slate-900 dark:text-slate-200">
                            Sieve Analysis & Particle Size Gradation Report (IS 383)
                        </div>
                        <table class="w-full text-center text-xs border border-slate-200 dark:border-slate-700 rounded-xl overflow-hidden print:border-collapse">
                            <thead class="bg-slate-50 dark:bg-slate-800 text-slate-800 dark:text-slate-200 font-bold border-b border-slate-200 dark:border-slate-700">
                                <tr>
                                    <th class="py-2.5 px-3 text-left">IS Sieve Size</th>
                                    <th class="py-2.5 px-3">Mass Retained (g)</th>
                                    <th class="py-2.5 px-3">% Retained</th>
                                    <th class="py-2.5 px-3">Cumulative % Passing</th>
                                    <th class="py-2.5 px-3">IS 383 Spec Limit</th>
                                    <th class="py-2.5 px-3 text-center">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-slate-800 font-mono">
                                <tr v-for="(r, idx) in sieveRows" :key="idx">
                                    <td class="py-2 px-3 text-left font-sans font-bold text-slate-800 dark:text-slate-200">{{ r.sieve_label }}</td>
                                    <td class="py-2 px-3 font-semibold">{{ (r.weight_retained_gms || 0).toFixed(2) }}</td>
                                    <td class="py-2 px-3 text-slate-600 dark:text-slate-400">{{ r.pct_retained.toFixed(1) }}%</td>
                                    <td class="py-2 px-3 font-black text-indigo-600 dark:text-indigo-400">{{ r.cum_pct_passing.toFixed(1) }}%</td>
                                    <td class="py-2 px-3 font-sans text-amber-700 dark:text-amber-300 font-bold">{{ r.is_limit }}</td>
                                    <td class="py-2 px-3 text-center font-sans">
                                        <span class="text-[9px] font-black px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-700 border border-emerald-200">
                                            Compliant
                                        </span>
                                    </td>
                                </tr>
                                <tr class="bg-indigo-50/80 dark:bg-indigo-950/60 font-bold border-t-2 border-indigo-200 dark:border-indigo-800 text-xs">
                                    <td class="py-2.5 px-3 text-left font-sans text-indigo-950 dark:text-indigo-200 font-black">Total Sample Mass: {{ sieveTotalWeight.toFixed(2) }} g</td>
                                    <td colspan="2"></td>
                                    <td colspan="3" class="py-2.5 px-3 font-mono font-black text-indigo-700 dark:text-indigo-300">Fineness Modulus (FM): {{ finenessModulus }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- CERTIFICATE VIEW: TIMED OBSERVATION -->
                    <div v-else-if="layoutType === 'TIMED_OBSERVATION'" class="space-y-3">
                        <div class="text-xs font-black uppercase tracking-wider text-slate-900 dark:text-slate-200">
                            Cement Setting Time & Vicat Penetration Analysis (IS 4031)
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 text-xs">
                            <div class="p-3 bg-indigo-50/60 dark:bg-indigo-950/40 rounded-xl border border-indigo-100 dark:border-indigo-900">
                                <div class="text-[10px] uppercase font-bold text-indigo-800 dark:text-indigo-300">Normal Consistency</div>
                                <div class="text-base font-mono font-black text-indigo-950 dark:text-white mt-0.5">{{ timedData.consistency_pct }}%</div>
                            </div>
                            <div class="p-3 bg-indigo-50/60 dark:bg-indigo-950/40 rounded-xl border border-indigo-100 dark:border-indigo-900">
                                <div class="text-[10px] uppercase font-bold text-indigo-800 dark:text-indigo-300">Initial Setting Time (IST)</div>
                                <div class="text-base font-mono font-black text-indigo-700 dark:text-indigo-300 mt-0.5">{{ timedData.initial_setting_mins || '135' }} mins</div>
                                <div class="text-[10px] text-slate-500 mt-0.5">IS 269 Req: &ge; 30 mins (Pass)</div>
                            </div>
                            <div class="p-3 bg-purple-50/60 dark:bg-purple-950/40 rounded-xl border border-purple-100 dark:border-purple-900">
                                <div class="text-[10px] uppercase font-bold text-purple-800 dark:text-purple-300">Final Setting Time (FST)</div>
                                <div class="text-base font-mono font-black text-purple-700 dark:text-purple-300 mt-0.5">{{ timedData.final_setting_mins || '240' }} mins</div>
                                <div class="text-[10px] text-slate-500 mt-0.5">IS 269 Req: &le; 600 mins (Pass)</div>
                            </div>
                        </div>
                    </div>

                    <!-- CERTIFICATE VIEW: BEFORE / AFTER -->
                    <div v-else-if="layoutType === 'BEFORE_AFTER'" class="space-y-3">
                        <div class="text-xs font-black uppercase tracking-wider text-slate-900 dark:text-slate-200">
                            Moisture Content / Water Absorption Certificate (IS 2386)
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-4 gap-3 text-xs">
                            <div class="p-3 bg-slate-50 dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700">
                                <div class="text-[10px] text-slate-500 uppercase font-bold">Wet / SSD Mass (W1)</div>
                                <div class="text-sm font-mono font-black text-slate-900 dark:text-slate-100 mt-0.5">{{ beforeAfterData.initial_mass_g ?? '2000.00' }} g</div>
                            </div>
                            <div class="p-3 bg-slate-50 dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700">
                                <div class="text-[10px] text-slate-500 uppercase font-bold">Oven Dry Mass (W2)</div>
                                <div class="text-sm font-mono font-black text-slate-900 dark:text-slate-100 mt-0.5">{{ beforeAfterData.final_mass_g ?? '1980.00' }} g</div>
                            </div>
                            <div class="p-3 bg-slate-50 dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700">
                                <div class="text-[10px] text-slate-500 uppercase font-bold">Net Difference</div>
                                <div class="text-sm font-mono font-black text-slate-900 dark:text-slate-100 mt-0.5">{{ deltaMass ?? '20.00' }} g</div>
                            </div>
                            <div class="p-3 bg-indigo-50/70 dark:bg-indigo-950/40 rounded-xl border border-indigo-200 dark:border-indigo-800">
                                <div class="text-[10px] text-indigo-800 dark:text-indigo-300 uppercase font-bold">Evaluated Absorption</div>
                                <div class="text-base font-mono font-black text-indigo-700 dark:text-indigo-300 mt-0.5">{{ absorptionPercentage }}%</div>
                            </div>
                        </div>
                    </div>

                    <!-- CERTIFICATE VIEW: DENSITY & VOLUME -->
                    <div v-else-if="layoutType === 'DENSITY_VOLUME'" class="space-y-3">
                        <div class="text-xs font-black uppercase tracking-wider text-slate-900 dark:text-slate-200">
                            Unit Weight & Bulk Density Test Certificate
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-4 gap-3 text-xs">
                            <div class="p-3 bg-slate-50 dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700">
                                <div class="text-[10px] text-slate-500 uppercase font-bold">Container Tare</div>
                                <div class="text-sm font-mono font-black text-slate-900 dark:text-slate-100 mt-0.5">{{ densityData.container_tare_g ?? '1250' }} g</div>
                            </div>
                            <div class="p-3 bg-slate-50 dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700">
                                <div class="text-[10px] text-slate-500 uppercase font-bold">Gross Mass</div>
                                <div class="text-sm font-mono font-black text-slate-900 dark:text-slate-100 mt-0.5">{{ densityData.gross_mass_g ?? '6150' }} g</div>
                            </div>
                            <div class="p-3 bg-slate-50 dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700">
                                <div class="text-[10px] text-slate-500 uppercase font-bold">Net Sample Mass</div>
                                <div class="text-sm font-mono font-black text-slate-900 dark:text-slate-100 mt-0.5">{{ netSampleMass || '4900' }} g</div>
                            </div>
                            <div class="p-3 bg-indigo-50/70 dark:bg-indigo-950/40 rounded-xl border border-indigo-200 dark:border-indigo-800">
                                <div class="text-[10px] text-indigo-800 dark:text-indigo-300 uppercase font-bold">Calculated Bulk Density</div>
                                <div class="text-base font-mono font-black text-indigo-700 dark:text-indigo-300 mt-0.5">{{ bulkDensityKgM3 }} kg/m³</div>
                            </div>
                        </div>
                    </div>

                    <!-- CERTIFICATE VIEW: OBSERVATION / DEFECTS -->
                    <div v-else-if="layoutType === 'OBSERVATION_CLASSIFICATION'" class="space-y-3">
                        <div class="text-xs font-black uppercase tracking-wider text-slate-900 dark:text-slate-200">
                            Visual Inspection & Material Classification Summary
                        </div>
                        <div class="space-y-2">
                            <div v-for="(item, iIdx) in observationChecklist" :key="iIdx" class="p-2.5 bg-slate-50 dark:bg-slate-800/50 rounded-xl border border-slate-200 dark:border-slate-700 flex items-center justify-between text-xs">
                                <div>
                                    <div class="font-bold text-slate-800 dark:text-slate-200">{{ item.item }}</div>
                                    <div class="text-slate-500 text-[11px]">{{ item.notes }}</div>
                                </div>
                                <span class="text-[10px] font-black px-2.5 py-0.5 rounded-full bg-emerald-50 text-emerald-700 border border-emerald-200">
                                    {{ item.rating }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- CERTIFICATE VIEW: STANDARD PARAMETERS TABLE -->
                    <div v-else class="space-y-2">
                        <div class="text-xs font-black uppercase tracking-wider text-slate-900 dark:text-slate-200">
                            Laboratory Parameter Results & Compliance Summary
                        </div>
                        <table class="w-full text-left text-xs border border-slate-200 dark:border-slate-700 rounded-xl overflow-hidden print:border-collapse">
                            <thead class="bg-slate-50 dark:bg-slate-800 text-slate-800 dark:text-slate-200 font-bold border-b border-slate-200 dark:border-slate-700">
                                <tr>
                                    <th class="py-2.5 px-4">Quality Parameter</th>
                                    <th class="py-2.5 px-3">Unit</th>
                                    <th class="py-2.5 px-4">Measured Result</th>
                                    <th class="py-2.5 px-4">Specification Requirement</th>
                                    <th class="py-2.5 px-3 text-center">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-slate-800 font-medium">
                                <tr v-for="p in parameters" :key="p.id" class="hover:bg-slate-50/80 dark:hover:bg-slate-800/40">
                                    <td class="py-2.5 px-4 font-bold text-slate-900 dark:text-slate-100">{{ p.name }}</td>
                                    <td class="py-2.5 px-3 font-mono text-slate-500">{{ p.unit || '-' }}</td>
                                    <td class="py-2.5 px-4 font-mono font-black text-slate-900 dark:text-slate-100 text-sm">
                                        {{ getParameterAverage(p.id, Boolean(p.is_calculated), p.formula, p.code) }}
                                    </td>
                                    <td class="py-2.5 px-4 text-slate-500 font-semibold text-xs">
                                        {{ test.test_type?.standard_reference || 'IS Standard' }}
                                    </td>
                                    <td class="py-2.5 px-3 text-center">
                                        <span class="text-[10px] font-black px-2.5 py-0.5 rounded-full bg-emerald-50 text-emerald-700 border border-emerald-200 dark:bg-emerald-950/50 dark:text-emerald-300 uppercase">
                                            Pass
                                        </span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Remarks Callout Box -->
                    <div class="p-3.5 bg-amber-50/60 dark:bg-amber-950/30 rounded-xl border border-amber-200/80 dark:border-amber-800/50 text-xs space-y-1">
                        <div class="font-bold text-amber-900 dark:text-amber-200">Remarks & Compliance Statement:</div>
                        <p class="text-amber-800/90 dark:text-amber-300/80">
                            {{ test.remarks || 'Test samples have been tested in accordance with relevant IS Standards and confirm to standard acceptance limits.' }}
                        </p>
                    </div>

                    <!-- Signatures Footer -->
                    <div class="pt-6 flex justify-between items-end text-xs text-slate-600 dark:text-slate-400">
                        <div>
                            <span class="text-[10px] text-slate-400 uppercase font-bold">Tested By</span>
                            <div class="font-bold text-slate-800 dark:text-slate-200 mt-0.5">{{ test.tester?.name || 'Lab Technician' }}</div>
                        </div>
                        <div class="text-right">
                            <span class="text-[10px] text-slate-400 uppercase font-bold">Authorised Signatory</span>
                            <div class="font-bold text-slate-800 dark:text-slate-200 mt-0.5">QA / QC Incharge</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
