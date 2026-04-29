import { defineStore } from 'pinia';
import { ref } from 'vue';

export interface EntityAddress {
    id?: number;
    entity_id?: number;
    address_type: number | string | null;
    line_1: string;
    line_2: string | null;
    city: string;
    zipcode: string | null;
    landmark: string | null;
    country_id: number | null;
    state_id: number | null;
    is_primary: number;
}

export interface EntityContact {
    id?: number;
    entity_id?: number;
    contact_type: number | string | null;
    contact_person: string;
    email: string | null;
    mobile: string | null;
    alt_mobile: string | null;
    alt_email: string | null;
    landline: string | null;
    is_primary: number;
}

export interface EntityBankAccount {
    id?: number;
    entity_id?: number;
    account_type: number | string | null;
    account_number: string;
    bank_name: string;
    bank_branch: string | null;
    ifsc_code: string | null;
    bank_address: string | null;
    is_primary: number;
}

export interface EntityTax {
    id?: number;
    entity_id?: number;
    tax_type: string | number | null;
    tax_number: string;
    country_id: number | null;
    state_id: number | null;
    is_primary: number;
}

export interface Entity {
    id: number;
    entity_type: number;
    parent_id: number | null;
    legal_name: string;
    alias: string | null;
    email: string | null;
    url: string | null;
    api_key: string | null;
    logo_file: string | null;
    description: string | null;
    time_zone: string | null;
    is_active: number;
    is_suspended: number;
    addresses: EntityAddress[];
    contacts: EntityContact[];
    bank_accounts: EntityBankAccount[];
    taxes: EntityTax[];
}

export const useEntityStore = defineStore('entity', () => {
    const entities = ref<Entity[]>([]);

    function setEntities(data: Entity[]) {
        entities.value = data;
    }

    function addEntity(item: Entity) {
        entities.value.push(item);
    }

    function updateEntity(updatedItem: Entity) {
        const index = entities.value.findIndex(a => a.id === updatedItem.id);
        if (index !== -1) {
            entities.value[index] = updatedItem;
        }
    }

    function removeEntity(id: number) {
        entities.value = entities.value.filter(a => a.id !== id);
    }

    return {
        entities,
        setEntities,
        addEntity,
        updateEntity,
        removeEntity
    };
});
