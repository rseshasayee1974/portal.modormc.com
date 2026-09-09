/**
 * Schwing ERP Integration - Two-Way Sync Orchestration Service
 * 
 * Orchestrates the full lifecycle:
 * 1. Fetch sales order & recipe data from our Portal endpoint.
 * 2. Send the Production / Process Order to the Schwing Stetter Cloud / machine.
 * 3. Pull actual batch consumption from the Schwing machine.
 * 4. Reflect the actual batch data and raw material consumption back into our Portal.
 */

import axios, { type AxiosRequestConfig } from 'axios';
import {
    sendProductionOrderToSchwing,
    formatSalesOrderForSchwing,
    type SchwingProductionOrderPayload,
    type SchwingOrderStatus
} from './productionOrderService';
import {
    pullBatchwiseConsumptionFromSchwing,
    aggregatePulledMaterials,
    type SchwingConsumptionPullRequest,
    type SchwingBatchItem,
    type SchwingConsumptionPullResponse
} from './consumptionPullService';
import {
    transformSchwingToBatchRecord,
    type SchwingConsumptionPushPayload
} from './consumptionPushService';

export interface SchwingSyncOptions {
    schwingBaseUrl?: string; // e.g. "https://schwing-cloud-api.domain.com"
    portalBaseUrl?: string;  // e.g. "/api" or base app URL
    axiosConfig?: AxiosRequestConfig;
}

export interface ReflectedBatchSyncResult {
    success: boolean;
    synced_batches_count: number;
    total_volume_m3: number;
    materials_summary_kg: Record<string, number>;
    batches: Array<{
        batch_no: string;
        order_no: string;
        qty_m3: number;
        start_time: string;
        end_time: string;
        truck_reg: string;
        driver: string;
        portal_batch_id?: number | string;
        status: 'created' | 'updated' | 'skipped' | 'failed';
        error?: string;
    }>;
}

/**
 * Step 1 & 2: Fetch data from our Portal and send Production Order to Schwing Stetter Machine.
 * 
 * @param salesOrderId The ID of the Sales Order in our portal
 * @param options Sync configuration (Schwing Cloud Base URL, plant info, order status)
 */
export async function sendPortalOrderToSchwing(
    salesOrderId: number | string,
    options: SchwingSyncOptions & {
        plantSerial?: string;
        plantType?: string;
        orderStatus?: SchwingOrderStatus;
    } = {}
) {
    const { schwingBaseUrl = '', plantSerial, plantType, orderStatus = 1 } = options;

    // 1. Fetch Sales Order with Site, Customer, and MixDesign details from our Portal endpoint
    const portalRes = await axios.get(`/sales-orders/${salesOrderId}`, {
        headers: { 'Accept': 'application/json' },
        ...(options.axiosConfig || {})
    });

    const salesOrderData = portalRes.data?.salesOrder || portalRes.data?.data || portalRes.data;
    if (!salesOrderData) {
        throw new Error(`[Schwing Sync] Could not retrieve Sales Order #${salesOrderId} from Portal endpoint.`);
    }

    // 2. Format into Schwing Production Order format
    const schwingPayload: SchwingProductionOrderPayload = formatSalesOrderForSchwing({
        plant: {
            serial_no: plantSerial || salesOrderData.plant?.serial_no || salesOrderData.plant_serial || 'PLANT-01',
            plant_type: plantType || salesOrderData.plant?.plant_type || 'CP 30',
        },
        salesOrder: salesOrderData,
        orderStatus
    });

    // 3. Dispatch to Schwing Cloud
    const schwingResponse = await sendProductionOrderToSchwing(schwingPayload, schwingBaseUrl, options.axiosConfig);

    return {
        success: true,
        order_no: schwingPayload.order_no,
        schwing_payload: schwingPayload,
        schwing_response: schwingResponse.data
    };
}

/**
 * Step 3 & 4: Pull actual batch consumption from Schwing Stetter Machine and reflect it into our Portal.
 * 
 * @param pullRequest Filter with plant serial, plant type, and date range
 * @param options Sync configuration (Schwing Base URL, Portal sync endpoints)
 */
export async function pullAndReflectSchwingData(
    pullRequest: SchwingConsumptionPullRequest,
    options: SchwingSyncOptions = {}
): Promise<ReflectedBatchSyncResult> {
    const { schwingBaseUrl = '' } = options;

    // 1. Pull batchwise consumption data from Schwing machine API
    const schwingResult: SchwingConsumptionPullResponse = await pullBatchwiseConsumptionFromSchwing(
        pullRequest,
        schwingBaseUrl,
        options.axiosConfig
    );

    const batches = schwingResult.data || [];
    const aggregated = aggregatePulledMaterials(batches);

    const syncResults: ReflectedBatchSyncResult['batches'] = [];

    // 2. Reflect each batch into our Portal backend
    for (const item of batches) {
        try {
            const transformed = transformSchwingToBatchRecord({
                plant_type: pullRequest.plant_type,
                plant_sl: pullRequest.plant_sl,
                order_no: item.order_no,
                batch_no: item.batch_no,
                cust_id: item.cust_id,
                site_id: item.site_id,
                truck_id: item.truck_id,
                driver: item.driver,
                start: item.start,
                end: item.end,
                rec_id: item.rec_id,
                rec_name: item.rec_name,
                qty: item.qty,
                mat: item.mat
            });

            // Post batch into Portal API (Batch & Materials ingestion)
            const saveRes = await axios.post('/api/production/batch', {
                ...transformed,
                sync_source: 'schwing_stetter_pull'
            }, {
                headers: { 'Accept': 'application/json' },
                ...(options.axiosConfig || {})
            }).catch(async () => {
                // Fallback to standard web batches store route if available
                return await axios.post('/batches', {
                    sales_order_id: item.order_no,
                    batch_no: item.batch_no,
                    batch_size: item.qty,
                    start_time: item.start,
                    end_time: item.end,
                    status: 4, // Completed
                    materials: item.mat.map(m => ({
                        material_name: m.item,
                        actual_qty: m.act
                    }))
                });
            });

            syncResults.push({
                batch_no: String(item.batch_no),
                order_no: String(item.order_no),
                qty_m3: parseFloat(String(item.qty)) || 0,
                start_time: item.start,
                end_time: item.end,
                truck_reg: item.truck_id,
                driver: item.driver,
                portal_batch_id: saveRes?.data?.id || saveRes?.data?.batch?.id,
                status: 'created'
            });
        } catch (err: any) {
            syncResults.push({
                batch_no: String(item.batch_no),
                order_no: String(item.order_no),
                qty_m3: parseFloat(String(item.qty)) || 0,
                start_time: item.start,
                end_time: item.end,
                truck_reg: item.truck_id,
                driver: item.driver,
                status: 'failed',
                error: err?.response?.data?.message || err.message || 'Sync error'
            });
        }
    }

    return {
        success: true,
        synced_batches_count: syncResults.filter(b => b.status === 'created' || b.status === 'updated').length,
        total_volume_m3: aggregated.total_volume_m3,
        materials_summary_kg: aggregated.materials_kg,
        batches: syncResults
    };
}

/**
 * Reflects an incoming real-time Push payload from Schwing into Portal database.
 * 
 * @param pushedPayload The raw payload received from Schwing PLC
 */
export async function reflectPushedBatchIntoPortal(pushedPayload: SchwingConsumptionPushPayload) {
    const transformed = transformSchwingToBatchRecord(pushedPayload);

    const response = await axios.post('/api/production/batch', {
        ...transformed,
        sync_source: 'schwing_stetter_push'
    }, {
        headers: { 'Accept': 'application/json' }
    });

    return {
        success: true,
        batch_no: transformed.batch_no,
        order_reference: transformed.order_reference,
        reflected_data: response.data
    };
}
