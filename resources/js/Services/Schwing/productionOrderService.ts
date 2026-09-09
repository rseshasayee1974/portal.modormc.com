/**
 * Schwing ERP Integration - Production / Process Order API Service
 * 
 * Manages and transfers production/process order data from ERP/Portal to Schwing Cloud.
 * Endpoint: POST /api/production__Order__data
 */

import axios, { type AxiosRequestConfig, type AxiosResponse } from 'axios';

export interface SchwingRecipeMaterialTarget {
    item: string;   // Recipe Material Name (e.g. "10 MM", "SAND", "Cem1")
    tar: string | number; // Recipe Target per Cu.m (e.g. "485")
}

export type SchwingOrderStatus = 1 | 2 | 3 | '1' | '2' | '3'; // 1: New, 2: Update, 3: Cancelled

export interface SchwingProductionOrderPayload {
    plant_sl: string;              // Mandatory: Schwing Plant Serial Number
    plant_type: string;            // Mandatory: Schwing Plant Type (e.g. "CP 30", "Stetter M1")
    order_no: number | string;     // Mandatory: Sales Order Number
    order_date: string;            // Mandatory: Format "yyyy-MM-dd"
    order_status: SchwingOrderStatus; // Mandatory: 1: New, 2: Update, 3: Cancelled
    qty: string | number;          // Mandatory: Ordered Quantity in Cu.m
    cust_id: string;               // Mandatory: Customer ID
    cust_name: string;             // Mandatory: Customer Name
    cust_add_l1?: string;          // Optional: Customer Address Line 1
    cust_add_l2?: string;          // Optional: Customer Address Line 2
    site_name: string;             // Mandatory: Site Name
    site_add_l1?: string;          // Optional: Site Address Line 1
    site_add_l2?: string;          // Optional: Site Address Line 2
    strength?: string;             // Optional: Strength (e.g. "M25")
    consistency?: string;          // Optional: Consistency
    slump?: string;                // Optional: Slump in mm
    wat_cem_ratio?: string | number; // Optional: Water-Cement Ratio
    mix_time: number;              // Mandatory: Mixer Mixing Time in Seconds (e.g. 30)
    mix_dis_time: number;          // Mandatory: Mixer Discharge Time in Seconds (e.g. 15)
    pre_mix_time?: number;         // Optional: Pre-Mixing Time in Seconds (e.g. 10)
    rec_id: string;                // Mandatory: Recipe ID (e.g. "M25")
    rec_name: string;              // Mandatory: Recipe Name
    mat: SchwingRecipeMaterialTarget[]; // Mandatory: Recipe materials & targets per m3
}

export interface SchwingApiResponse<T = any> {
    success?: boolean;
    status?: string | number;
    message?: string;
    data?: T;
    [key: string]: any;
}

/**
 * Sends a Production/Process Order to Schwing Cloud.
 * 
 * @param payload SchwingProductionOrderPayload - The formatted production order
 * @param baseUrl Optional Schwing base URL (defaults to environment config or Schwing Cloud host)
 * @param axiosConfig Optional custom axios request config
 */
export async function sendProductionOrderToSchwing(
    payload: SchwingProductionOrderPayload,
    baseUrl: string = '',
    axiosConfig: AxiosRequestConfig = {}
): Promise<AxiosResponse<SchwingApiResponse>> {
    const url = `${baseUrl ? baseUrl.replace(/\/+$/, '') : ''}/api/production__Order__data`;

    // Validate mandatory fields
    validateProductionOrderPayload(payload);

    return axios.post<SchwingApiResponse>(url, payload, {
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            ...(axiosConfig.headers || {})
        },
        ...axiosConfig
    });
}

/**
 * Helper to transform portal SalesOrder, Site, Plant and MixDesign models into Schwing's payload format.
 */
export function formatSalesOrderForSchwing(params: {
    plant: { serial_no?: string; plant_type?: string };
    salesOrder: {
        id?: number | string;
        order_no: string | number;
        prefix?: string;
        order_date?: string;
        total_qty: number | string;
        customer?: { id?: number | string; code?: string; legal_name?: string; address_line_1?: string; address_line_2?: string; city?: string };
        site?: { name?: string; address_line_1?: string; address_line_2?: string; city?: string };
        mix_design?: {
            design_code?: string;
            design_name?: string;
            grade?: string;
            slump?: string;
            w_c_ratio?: string | number;
            mixing_time_sec?: number;
            discharge_time_sec?: number;
            materials?: Array<{ name: string; target_qty: number | string }>;
        };
    };
    orderStatus?: SchwingOrderStatus;
}): SchwingProductionOrderPayload {
    const { plant, salesOrder, orderStatus = 1 } = params;
    const so = salesOrder;
    const cust = so.customer;
    const site = so.site;
    const mix = so.mix_design;

    const formattedDate = so.order_date
        ? String(so.order_date).substring(0, 10)
        : new Date().toISOString().substring(0, 10);

    const materials: SchwingRecipeMaterialTarget[] = (mix?.materials || []).map(m => ({
        item: m.name,
        tar: String(m.target_qty ?? 0)
    }));

    return {
        plant_sl: plant.serial_no || '',
        plant_type: plant.plant_type || '',
        order_no: String(so.order_no || so.id || ''),
        order_date: formattedDate,
        order_status: orderStatus,
        qty: String(so.total_qty || 0),
        cust_id: String(cust?.code || cust?.id || ''),
        cust_name: cust?.legal_name || '',
        cust_add_l1: cust?.address_line_1 || '',
        cust_add_l2: [cust?.address_line_2, cust?.city].filter(Boolean).join(', ') || '',
        site_name: site?.name || '',
        site_add_l1: site?.address_line_1 || '',
        site_add_l2: [site?.address_line_2, site?.city].filter(Boolean).join(', ') || '',
        strength: mix?.grade || '',
        consistency: '',
        slump: mix?.slump || '',
        wat_cem_ratio: mix?.w_c_ratio || '',
        mix_time: mix?.mixing_time_sec || 0,
        mix_dis_time: mix?.discharge_time_sec || 0,
        pre_mix_time: 0,
        rec_id: mix?.design_code || '',
        rec_name: mix?.design_name || '',
        mat: materials.length > 0 ? materials : [
            { item: '', tar: '' },
            { item: '', tar: '' },
            { item: '', tar: '' },
            { item: '', tar: '' }
        ]
    };
}

/**
 * Validates mandatory fields before sending to Schwing.
 */
export function validateProductionOrderPayload(payload: SchwingProductionOrderPayload): void {
    const required: (keyof SchwingProductionOrderPayload)[] = [
        'plant_sl',
        'plant_type',
        'order_no',
        'order_date',
        'order_status',
        'qty',
        'cust_id',
        'cust_name',
        'site_name',
        'mix_time',
        'mix_dis_time',
        'rec_id',
        'rec_name',
        'mat'
    ];

    for (const field of required) {
        if (payload[field] === undefined || payload[field] === null || payload[field] === '') {
            throw new Error(`[Schwing API] Missing mandatory field: "${field}" in Production Order payload.`);
        }
    }

    if (!Array.isArray(payload.mat) || payload.mat.length === 0) {
        throw new Error(`[Schwing API] Production Order must have at least one material in "mat" array.`);
    }
}
