/**
 * Schwing ERP Integration - Actual Consumption API (Data Push from Schwing)
 * 
 * Handles incoming/outgoing actual material consumption and batch sheet data post-production.
 * Used for interfacing batch production results between Schwing batch controllers and SAP/Portal.
 */

import axios, { type AxiosRequestConfig, type AxiosResponse } from 'axios';

export interface SchwingMaterialActualConsumption {
    item: string;       // Material Name (e.g., "10MM", "Sand", "20MM", "CEM2", "WATER")
    act: number | string; // Actual Consumption quantity in Kgs (e.g., 3629)
}

export interface SchwingConsumptionPushPayload {
    plant_type: string;   // Type of the Schwing plant (e.g. "CP 30")
    plant_sl: string;     // Serial number of the Schwing plant (e.g. "474")
    order_no: string;     // Order Number as per batching system (e.g. "01")
    batch_no: string;     // Batch number as per batching system (e.g. "4")
    cust_id: string;      // Customer ID as per batching system (e.g. "01")
    site_id: string;      // Site ID / Name as per batching system (e.g. "PANCHSHIL")
    truck_id: string;     // Truck Reg No. as per batching system (e.g. "MH26BE8292")
    driver: string;       // Truck driver name as per batching system (e.g. "DATTA")
    start: string;        // Batch Start Time in "YYYY-MM-DD HH:mm:ss" format
    end: string;          // Batch End Time in "YYYY-MM-DD HH:mm:ss" format
    rec_id: string;       // Recipe ID as per batching system (e.g. "M30")
    rec_name: string;     // Recipe Name as per batching system (e.g. "M30")
    qty: number | string; // Production qty as per batching system (e.g. "7.0002" m3)
    mat: SchwingMaterialActualConsumption[]; // Array of material consumption details in Kgs
}

export interface ConsumptionPushProcessResult {
    success: boolean;
    batch_no: string;
    order_no: string;
    total_qty: number;
    materials_count: number;
    message: string;
    data?: any;
}

/**
 * Pushes actual consumption data to a target ERP, SAP or custom endpoint.
 * 
 * @param payload SchwingConsumptionPushPayload - The batch consumption payload
 * @param endpointUrl Target receiver endpoint URL
 * @param axiosConfig Optional axios request configuration
 */
export async function pushConsumptionData(
    payload: SchwingConsumptionPushPayload,
    endpointUrl: string,
    axiosConfig: AxiosRequestConfig = {}
): Promise<AxiosResponse<any>> {
    validateConsumptionPushPayload(payload);

    return axios.post(endpointUrl, payload, {
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            ...(axiosConfig.headers || {})
        },
        ...axiosConfig
    });
}

/**
 * Validates the structure and mandatory fields of a pushed consumption batch.
 */
export function validateConsumptionPushPayload(payload: SchwingConsumptionPushPayload): boolean {
    const required: (keyof SchwingConsumptionPushPayload)[] = [
        'plant_type',
        'plant_sl',
        'order_no',
        'batch_no',
        'cust_id',
        'site_id',
        'truck_id',
        'driver',
        'start',
        'end',
        'rec_id',
        'rec_name',
        'qty',
        'mat'
    ];

    for (const field of required) {
        if (payload[field] === undefined || payload[field] === null || payload[field] === '') {
            throw new Error(`[Schwing Consumption Push] Missing required field: "${field}"`);
        }
    }

    if (!Array.isArray(payload.mat) || payload.mat.length === 0) {
        throw new Error(`[Schwing Consumption Push] Batch payload must contain at least one material in "mat" array.`);
    }

    return true;
}

/**
 * Normalizes and transforms an incoming Schwing Push batch into Portal model records (Batch & BatchMaterial).
 * 
 * @param payload SchwingConsumptionPushPayload
 * @returns Standardized internal batch object for database synchronization
 */
export function transformSchwingToBatchRecord(payload: SchwingConsumptionPushPayload) {
    validateConsumptionPushPayload(payload);

    const parsedQty = parseFloat(String(payload.qty)) || 0;
    const totalMaterialKgs = payload.mat.reduce((acc, m) => acc + (parseFloat(String(m.act)) || 0), 0);

    return {
        batch_no: payload.batch_no,
        order_reference: payload.order_no,
        plant_serial: payload.plant_sl,
        plant_type: payload.plant_type,
        customer_code: payload.cust_id,
        site_name: payload.site_id,
        transit_mixer_reg: payload.truck_id,
        driver_name: payload.driver,
        recipe_code: payload.rec_id,
        recipe_name: payload.rec_name,
        batch_size_m3: parsedQty,
        total_material_kgs: Math.round(totalMaterialKgs * 100) / 100,
        start_time: payload.start,
        end_time: payload.end,
        materials: payload.mat.map(item => ({
            material_name: item.item,
            actual_qty_kg: parseFloat(String(item.act)) || 0
        }))
    };
}
