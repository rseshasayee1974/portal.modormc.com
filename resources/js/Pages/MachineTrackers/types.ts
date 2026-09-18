export interface MachineTracker {
    id: number;
    plant_id: number;
    machine_id: number;
    operation_type: string | null;
    category: string | null;
    operator_id: number | null;
    opening: any;
    closing: any;
    odometer_start: number;
    odometer_end: number;
    hourmeter_start: number;
    hourmeter_end: number;
    eb_start: number;
    eb_close: number;
    opening_hsd: number;
    closing_hsd: number;
    notes: string | null;
    fuel: number;
    fuel_filled_on: any;
    last_fuel_filled_km: number;
    fuel_filled_km: number;
    pump_name: string | null;
    pump_reading: string | null;
    amount: number;
    shift: number;
    created?: any;
    created_by?: number;
    company_id?: number;
    machine?: {
        id: number;
        registration: string;
    };
    operator?: {
        id: number;
        username: string;
    };
}

export interface OptionItem {
    label: string;
    value: any;
}

export const SHIFT_OPTIONS: OptionItem[] = [
    { label: 'Day Shift', value: 1 },
    { label: 'Night Shift', value: 2 },
    { label: 'General Shift', value: 3 },
    { label: 'Not Specified', value: -1 }
];

export const getInitialTrackerForm = () => ({
    machine_id: null as number | null,
    operation_type: '',
    category: '',
    operator_id: null as number | null,
    opening: null as any,
    closing: null as any,
    odometer_start: 0,
    odometer_end: 0,
    hourmeter_start: 0,
    hourmeter_end: 0,
    eb_start: 0,
    eb_close: 0,
    opening_hsd: 0,
    closing_hsd: 0,
    notes: '',
    fuel: 0,
    fuel_filled_on: null as any,
    last_fuel_filled_km: 0,
    fuel_filled_km: 0,
    pump_name: '',
    pump_reading: '',
    amount: 0,
    shift: -1
});
