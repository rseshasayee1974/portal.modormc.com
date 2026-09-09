/**
 * Schwing ERP Integration - Actual Consumption API (Data Pull from Schwing)
 * 
 * Retrieves batchwise material consumption data from a specified plant within a given date range.
 * Endpoint: POST api/Consumption__Data
 */

import axios, { type AxiosRequestConfig, type AxiosResponse } from 'axios';
import { type SchwingConsumptionPushPayload, type SchwingMaterialActualConsumption } from './consumptionPushService';

export interface SchwingConsumptionPullRequest {
    json_type?: string;   // Default: "Batchwise Material Cons"
    plant_sl: string;     // Mandatory: Serial number of the Schwing plant
    plant_type: string;   // Mandatory: Type of the Schwing plant (e.g. "CP 30")
    start_date: string;   // Mandatory: Start date in "YYYY-MM-DD HH:mm:ss" format
    end_date: string;     // Mandatory: End date in "YYYY-MM-DD HH:mm:ss" format
}

export interface SchwingBatchItem {
    order_no: string;     // Order Number as per batching system
    batch_no: string;     // Batch number as per batching system
    cust_id: string;      // Customer ID as per batching system
    site_id: string;      // Site ID / Name as per batching system
    truck_id: string;     // Truck Reg No. as per batching system
    driver: string;       // Truck driver name as per batching system
    start: string;        // Batch Start Time in "YYYY-MM-DD HH:mm:ss" format
    end: string;          // Batch End Time in "YYYY-MM-DD HH:mm:ss" format
    rec_id: string;       // Recipe ID as per batching system
    rec_name: string;     // Recipe Name as per batching system
    qty: number | string; // Production qty in Cu.m
    mat: SchwingMaterialActualConsumption[]; // Material actual consumptions in Kgs
}

export interface SchwingConsumptionPullResponse {
    json_status: '1' | '0' | string; // "1": Success, "0": Error
    error_msg: string;               // Error description or "success" / "Material Cons Data"
    data: SchwingBatchItem[];        // Array of batch records with material consumption
}

/**
 * Pulls batchwise actual consumption data from Schwing Cloud for a specific plant and date range.
 * 
 * @param filter SchwingConsumptionPullRequest - Plant serial, type, start_date and end_date
 * @param baseUrl Optional Schwing base URL
 * @param axiosConfig Optional axios request configuration
 */
export async function pullBatchwiseConsumptionFromSchwing(
    filter: SchwingConsumptionPullRequest,
    baseUrl: string = '',
    axiosConfig: AxiosRequestConfig = {}
): Promise<SchwingConsumptionPullResponse> {
    const url = `${baseUrl ? baseUrl.replace(/\/+$/, '') : ''}/api/Consumption__Data`;

    // Validate mandatory parameters
    validateConsumptionPullRequest(filter);

    const payload = {
        json_type: filter.json_type || 'Batchwise Material Cons',
        plant_sl: filter.plant_sl,
        plant_type: filter.plant_type,
        start_date: normalizeDateTime(filter.start_date),
        end_date: normalizeDateTime(filter.end_date)
    };

    // Note: Schwing specification mentions Content-Type: text/plain or application/json.
    const res: AxiosResponse<any> = await axios.post(url, payload, {
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            ...(axiosConfig.headers || {})
        },
        ...axiosConfig
    });

    const responseData = typeof res.data === 'string' ? JSON.parse(res.data) : res.data;

    if (responseData.json_status === '0' || responseData.json_status === 0) {
        throw new Error(`[Schwing Consumption Pull Error]: ${responseData.error_msg || 'Failed to retrieve material consumption data.'}`);
    }

    return {
        json_status: String(responseData.json_status || '1'),
        error_msg: responseData.error_msg || 'Success',
        data: Array.isArray(responseData.data) ? responseData.data : []
    };
}

/**
 * Validates pull request parameters.
 */
export function validateConsumptionPullRequest(filter: SchwingConsumptionPullRequest): void {
    if (!filter.plant_sl) {
        throw new Error('[Schwing Consumption Pull] Missing mandatory parameter: "plant_sl".');
    }
    if (!filter.plant_type) {
        throw new Error('[Schwing Consumption Pull] Missing mandatory parameter: "plant_type".');
    }
    if (!filter.start_date) {
        throw new Error('[Schwing Consumption Pull] Missing mandatory parameter: "start_date".');
    }
    if (!filter.end_date) {
        throw new Error('[Schwing Consumption Pull] Missing mandatory parameter: "end_date".');
    }
}

/**
 * Helper to ensure standard "YYYY-MM-DD HH:mm:ss" format.
 */
function normalizeDateTime(dateStr: string): string {
    if (!dateStr) return '';
    // If it's already YYYY-MM-DD HH:mm:ss
    if (/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/.test(dateStr)) {
        return dateStr;
    }
    // If it's date only YYYY-MM-DD
    if (/^\d{4}-\d{2}-\d{2}$/.test(dateStr)) {
        return `${dateStr} 00:00:00`;
    }
    // Try standard Date parsing
    const d = new Date(dateStr);
    if (!isNaN(d.getTime())) {
        const yyyy = d.getFullYear();
        const mm = String(d.getMonth() + 1).padStart(2, '0');
        const dd = String(d.getDate()).padStart(2, '0');
        const hh = String(d.getHours()).padStart(2, '0');
        const min = String(d.getMinutes()).padStart(2, '0');
        const ss = String(d.getSeconds()).padStart(2, '0');
        return `${yyyy}-${mm}-${dd} ${hh}:${min}:${ss}`;
    }
    return dateStr;
}

/**
 * Summarizes material quantities across all batches in a pulled consumption dataset.
 * 
 * @param batches Array of SchwingBatchItem
 * @returns Aggregated material consumption summary
 */
export function aggregatePulledMaterials(batches: SchwingBatchItem[]) {
    let totalBatchVolumeM3 = 0;
    const materialSummary: Record<string, number> = {};

    for (const batch of batches) {
        totalBatchVolumeM3 += parseFloat(String(batch.qty)) || 0;
        for (const mat of batch.mat || []) {
            const matName = String(mat.item).toUpperCase().trim();
            const matQty = parseFloat(String(mat.act)) || 0;
            materialSummary[matName] = (materialSummary[matName] || 0) + matQty;
        }
    }

    return {
        total_batches: batches.length,
        total_volume_m3: Math.round(totalBatchVolumeM3 * 1000) / 1000,
        materials_kg: materialSummary
    };
}
