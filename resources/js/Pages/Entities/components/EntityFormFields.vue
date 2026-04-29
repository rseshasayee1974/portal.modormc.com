<script setup lang="ts">
import BaseInput from '@/Components/Base/BaseInput.vue';
import BaseSelect from '@/Components/Base/BaseSelect.vue';
import Textarea from 'primevue/textarea';
import ToggleSwitch from 'primevue/toggleswitch';
import Tabs from 'primevue/tabs';
import TabList from 'primevue/tablist';
import Tab from 'primevue/tab';
import TabPanels from 'primevue/tabpanels';
import TabPanel from 'primevue/tabpanel';
import Button from 'primevue/button';
import type { EntityAddress, EntityContact, EntityBankAccount } from '@/Pages/Entities/useEntityStore';

const props = defineProps<{
    form: any;
    entityTypes: { id: number; type: string }[];
    addressTypes: { id: number; type: string }[];
    contactTypes: { id: number; type: string }[];
    bankAccountTypes: { id: number; type: string }[];
    countries: { id: number; country_name: string }[];
    stateCodes: { id: number; state_name: string; state_code: string; country_id: number }[];
    readonly?: boolean;
}>();

const filteredStates = (countryId: number | null) => {
    if (!countryId) return props.stateCodes;
    return props.stateCodes.filter(s => s.country_id === countryId);
};

const addAddress = () => props.form.addresses.push({ address_type: props.addressTypes?.[0]?.id || null, line_1: '', line_2: null, city: '', zipcode: null, landmark: null, country_id: null, state_id: null, is_primary: 0 } as EntityAddress);
const addContact = () => props.form.contacts.push({ contact_type: props.contactTypes?.[0]?.id || null, contact_person: '', email: null, mobile: null, alt_mobile: null, alt_email: null, landline: null, is_primary: 0 } as EntityContact);
const addBank = () => props.form.bank_accounts.push({ account_type: props.bankAccountTypes?.[0]?.id || null, account_number: '', bank_name: '', bank_branch: null, ifsc_code: null, bank_address: null, is_primary: 0 } as EntityBankAccount);
const addTax = () => props.form.taxes.push({ tax_type: 'GST', tax_number: '', country_id: null, state_id: null, is_primary: 0 });

const removeRel = (arr: any[], idx: number) => arr.splice(idx, 1);
const taxTypeOptions = ['GST', 'PAN', 'VAT', 'TIN', 'Service Tax', 'CST'];
</script>

<template>
    <Tabs value="0">
        <TabList>
            <Tab value="0">
                <i class="pi pi-info-circle mr-2 text-xs"></i>General
            </Tab>
            <Tab value="1">
                <i class="pi pi-map-marker mr-2 text-xs"></i>Addresses
                <span v-if="form.addresses?.length" class="ml-1.5 flex items-center justify-center w-4 h-4 rounded-full bg-violet-600 text-white text-[8px] font-black">{{ form.addresses.length }}</span>
            </Tab>
            <Tab value="2">
                <i class="pi pi-phone mr-2 text-xs"></i>Contacts
                <span v-if="form.contacts?.length" class="ml-1.5 flex items-center justify-center w-4 h-4 rounded-full bg-violet-600 text-white text-[8px] font-black">{{ form.contacts.length }}</span>
            </Tab>
            <Tab value="3">
                <i class="pi pi-credit-card mr-2 text-xs"></i>Banking
                <span v-if="form.bank_accounts?.length" class="ml-1.5 flex items-center justify-center w-4 h-4 rounded-full bg-violet-600 text-white text-[8px] font-black">{{ form.bank_accounts.length }}</span>
            </Tab>
            <Tab value="4">
                <i class="pi pi-percentage mr-2 text-xs"></i>Taxes
                <span v-if="form.taxes?.length" class="ml-1.5 flex items-center justify-center w-4 h-4 rounded-full bg-violet-600 text-white text-[8px] font-black">{{ form.taxes.length }}</span>
            </Tab>
        </TabList>

        <TabPanels>
            <!-- General Tab -->
            <TabPanel value="0">
                <div class="grid grid-cols-12  gap-4 pt-4">
                    <!-- Legal Name -->
                    <div class="col-span-12 md:col-span-3 flex flex-col gap-1">
                        <label class="text-[10px] font-bold uppercase text-gray-400 tracking-widest">Legal Name <span class="text-red-400">*</span></label>
                        <BaseInput v-model="form.legal_name" fluid :disabled="readonly" placeholder="Full registered legal name" />
                        <small v-if="form.errors?.legal_name" class="text-red-500 text-[10px]">{{ form.errors.legal_name }}</small>
                    </div>

                    <!-- Alias -->
                    <div class="col-span-12 md:col-span-3 flex flex-col gap-1">
                        <label class="text-[10px] font-bold uppercase text-gray-400 tracking-widest">Alias / Short Name</label>
                        <BaseInput v-model="form.alias" fluid :disabled="readonly" placeholder="e.g. ACME Corp" />
                    </div>

                    <!-- Entity Type -->
                    <div class="col-span-12 md:col-span-3 flex flex-col gap-1">
                        <label class="text-[10px] font-bold uppercase text-gray-400 tracking-widest">Entity Type</label>
                        <BaseSelect v-model="form.entity_type" :options="entityTypes" optionLabel="type" optionValue="id" fluid :disabled="readonly" placeholder="Select type" />
                        <small v-if="form.errors?.entity_type" class="text-red-500 text-[10px]">{{ form.errors.entity_type }}</small>
                    </div>

                    <!-- Email -->
                    <div class="col-span-12 md:col-span-3 flex flex-col gap-1">
                        <label class="text-[10px] font-bold uppercase text-gray-400 tracking-widest">Email</label>
                        <BaseInput v-model="form.email" fluid :disabled="readonly" placeholder="contact@company.com" />
                    </div>

                    <!-- URL -->
                    <div class="col-span-12 md:col-span-3 flex flex-col gap-1">
                        <label class="text-[10px] font-bold uppercase text-gray-400 tracking-widest">Website URL</label>
                        <BaseInput v-model="form.url" fluid :disabled="readonly" placeholder="https://www.company.com" />
                    </div>

                    <!-- Description -->
                    <div class="col-span-12 md:col-span-3 flex flex-col gap-1">
                        <label class="text-[10px] font-bold uppercase text-gray-400 tracking-widest">Description</label>
                        <Textarea v-model="form.description" rows="2" fluid :disabled="readonly" placeholder="Brief description of this entity..." />
                    </div>

                    <!-- Toggles -->
                    <div class="col-span-12 md:col-span-3 flex items-center gap-4 p-3 bg-gray-50 dark:bg-gray-800/50 rounded-xl border border-gray-100 dark:border-gray-700">
                        <ToggleSwitch v-model="form.is_active" :disabled="readonly" />
                        <div>
                            <span class="text-xs font-bold uppercase text-gray-700 dark:text-gray-300">Active Status</span>
                            <p class="text-[10px] text-gray-400 mt-0.5">Entity is operational and visible</p>
                        </div>
                    </div>
                    <div class="col-span-12 md:col-span-3 flex items-center gap-4 p-3 bg-red-50 dark:bg-red-900/10 rounded-xl border border-red-100 dark:border-red-900/30">
                        <ToggleSwitch v-model="form.is_suspended" :disabled="readonly" />
                        <div>
                            <span class="text-xs font-bold uppercase text-red-600 dark:text-red-400">Suspended</span>
                            <p class="text-[10px] text-gray-400 mt-0.5">Temporarily restrict all operations</p>
                        </div>
                    </div>
                </div>
            </TabPanel>

            <!-- Addresses Tab -->
            <TabPanel value="1">
                <div class="flex flex-col gap-3 pt-4">
                    <div
                        v-for="(addr, i) in form.addresses"
                        :key="i"
                        class="relative p-4 border border-gray-200 dark:border-gray-700 rounded-xl bg-gray-50/50 dark:bg-gray-800/30 flex flex-col gap-3"
                    >
                        <div class="flex items-center justify-between mb-1">
                            <span class="text-[10px] font-black uppercase text-gray-400 tracking-widest">Address #{{ i + 1 }}</span>
                            <Button v-if="!readonly" icon="pi pi-trash" severity="danger" text size="small" @click="removeRel(form.addresses, i)" />
                        </div>
                        <div class="grid grid-cols-12 gap-3">
                            <div class="col-span-12 md:col-span-4 flex flex-col gap-1">
                                <label class="text-[10px] font-bold uppercase text-gray-400">Type</label>
                                <BaseSelect v-model="addr.address_type" :options="addressTypes" optionLabel="type" optionValue="id" placeholder="Address Type" :disabled="readonly" fluid />
                            </div>
                            <div class="col-span-12 md:col-span-8 flex items-center gap-3 pt-6">
                                <ToggleSwitch v-model="addr.is_primary" :trueValue="1" :falseValue="0" :disabled="readonly" />
                                <span class="text-xs font-bold text-gray-500 uppercase">Primary Address</span>
                            </div>
                            
                            <div class="col-span-12 md:col-span-6 flex flex-col gap-1">
                                <label class="text-[10px] font-bold uppercase text-gray-400">Address line 1</label>
                                <BaseInput v-model="addr.line_1" placeholder="Plot No, Building..." :disabled="readonly" fluid />
                            </div>
                            <div class="col-span-12 md:col-span-6 flex flex-col gap-1">
                                <label class="text-[10px] font-bold uppercase text-gray-400">Address line 2</label>
                                <BaseInput v-model="addr.line_2" placeholder="Street, Area..." :disabled="readonly" fluid />
                            </div>

                            <div class="col-span-12 md:col-span-3 flex flex-col gap-1">
                                <label class="text-[10px] font-bold uppercase text-gray-400">City</label>
                                <BaseInput v-model="addr.city" placeholder="City" :disabled="readonly" fluid />
                            </div>
                            <div class="col-span-12 md:col-span-3 flex flex-col gap-1">
                                <label class="text-[10px] font-bold uppercase text-gray-400">Zipcode</label>
                                <BaseInput v-model="addr.zipcode" placeholder="Zipcode" :disabled="readonly" fluid />
                            </div>
                            <div class="col-span-12 md:col-span-3 flex flex-col gap-1">
                                <label class="text-[10px] font-bold uppercase text-gray-400">Country</label>
                                <BaseSelect v-model="addr.country_id" :options="countries" optionLabel="country_name" optionValue="id" placeholder="Country" :disabled="readonly" fluid />
                            </div>
                            <div class="col-span-12 md:col-span-3 flex flex-col gap-1">
                                <label class="text-[10px] font-bold uppercase text-gray-400">State</label>
                                <BaseSelect v-model="addr.state_id" :options="filteredStates(addr.country_id)" optionLabel="state_name" optionValue="id" placeholder="State" :disabled="readonly" fluid />
                            </div>
                        </div>
                    </div>

                    <Button
                        v-if="!readonly"
                        label="Add Address"
                        icon="pi pi-plus"
                        text
                        severity="secondary"
                        class="self-start !text-violet-600 hover:!bg-violet-50"
                        @click="addAddress"
                    />
                    <div v-if="!form.addresses?.length" class="text-center py-6 text-gray-400 text-sm">
                        <i class="pi pi-map-marker text-2xl mb-2 block opacity-30"></i>
                        No addresses added yet.
                    </div>
                </div>
            </TabPanel>

            <!-- Contacts Tab -->
            <TabPanel value="2">
                <div class="flex flex-col gap-3 pt-4">
                    <div
                        v-for="(cont, i) in form.contacts"
                        :key="i"
                        class="relative p-4 border border-gray-200 dark:border-gray-700 rounded-xl bg-gray-50/50 dark:bg-gray-800/30 flex flex-col gap-3"
                    >
                        <div class="flex items-center justify-between mb-1">
                            <span class="text-[10px] font-black uppercase text-gray-400 tracking-widest">Contact #{{ i + 1 }}</span>
                            <Button v-if="!readonly" icon="pi pi-trash" severity="danger" text size="small" @click="removeRel(form.contacts, i)" />
                        </div>
                        <div class="grid grid-cols-12 gap-3">
                            <div class="col-span-12 md:col-span-4 flex flex-col gap-1">
                                <label class="text-[10px] font-bold uppercase text-gray-400">Type</label>
                                <BaseSelect v-model="cont.contact_type" :options="contactTypes" optionLabel="type" optionValue="id" placeholder="Contact Type" :disabled="readonly" fluid />
                            </div>
                            <div class="col-span-12 md:col-span-8 flex items-center gap-2 pt-6">
                                <ToggleSwitch v-model="cont.is_primary" :trueValue="1" :falseValue="0" :disabled="readonly" />
                                <span class="text-xs font-bold text-gray-500 uppercase">Primary Contact</span>
                            </div>
                            
                            <div class="col-span-12 md:col-span-6 flex flex-col gap-1">
                                <label class="text-[10px] font-bold uppercase text-gray-400">Contact Person</label>
                                <BaseInput v-model="cont.contact_person" placeholder="Name" :disabled="readonly" fluid />
                            </div>
                            <div class="col-span-12 md:col-span-3 flex flex-col gap-1">
                                <label class="text-[10px] font-bold uppercase text-gray-400">Mobile</label>
                                <BaseInput v-model="cont.mobile" placeholder="Mobile" :disabled="readonly" fluid />
                            </div>
                            <div class="col-span-12 md:col-span-3 flex flex-col gap-1">
                                <label class="text-[10px] font-bold uppercase text-gray-400">Alt Mobile</label>
                                <BaseInput v-model="cont.alt_mobile" placeholder="Alternate" :disabled="readonly" fluid />
                            </div>

                            <div class="col-span-12 md:col-span-6 flex flex-col gap-1">
                                <label class="text-[10px] font-bold uppercase text-gray-400">Email Address</label>
                                <BaseInput v-model="cont.email" placeholder="Email" :disabled="readonly" fluid />
                            </div>
                            <div class="col-span-12 md:col-span-6 flex flex-col gap-1">
                                <label class="text-[10px] font-bold uppercase text-gray-400">Alt Email</label>
                                <BaseInput v-model="cont.alt_email" placeholder="Secondary Email" :disabled="readonly" fluid />
                            </div>
                        </div>
                    </div>

                    <Button
                        v-if="!readonly"
                        label="Add Contact"
                        icon="pi pi-plus"
                        text
                        severity="secondary"
                        class="self-start !text-violet-600 hover:!bg-violet-50"
                        @click="addContact"
                    />
                    <div v-if="!form.contacts?.length" class="text-center py-6 text-gray-400 text-sm">
                        <i class="pi pi-phone text-2xl mb-2 block opacity-30"></i>
                        No contacts added yet.
                    </div>
                </div>
            </TabPanel>

            <!-- Banking Tab -->
            <TabPanel value="3">
                <div class="flex flex-col gap-3 pt-4">
                    <div
                        v-for="(bank, i) in form.bank_accounts"
                        :key="i"
                        class="relative p-4 border border-gray-200 dark:border-gray-700 rounded-xl bg-gray-50/50 dark:bg-gray-800/30 flex flex-col gap-3"
                    >
                        <div class="flex items-center justify-between mb-1">
                            <span class="text-[10px] font-black uppercase text-gray-400 tracking-widest">Bank Account #{{ i + 1 }}</span>
                            <Button v-if="!readonly" icon="pi pi-trash" severity="danger" text size="small" @click="removeRel(form.bank_accounts, i)" />
                        </div>
                        <div class="grid grid-cols-12 gap-3">
                            <div class="col-span-12 md:col-span-4 flex flex-col gap-1">
                                <label class="text-[10px] font-bold uppercase text-gray-400">Account Type</label>
                                <BaseSelect v-model="bank.account_type" :options="bankAccountTypes" optionLabel="type" optionValue="id" placeholder="Account Type" :disabled="readonly" fluid />
                            </div>
                            <div class="col-span-12 md:col-span-8 flex items-center gap-2 pt-6">
                                <ToggleSwitch v-model="bank.is_primary" :trueValue="1" :falseValue="0" :disabled="readonly" />
                                <span class="text-xs font-bold text-gray-500 uppercase">Primary Bank</span>
                            </div>

                            <div class="col-span-12 md:col-span-4 flex flex-col gap-1">
                                <label class="text-[10px] font-bold uppercase text-gray-400">Bank Name</label>
                                <BaseInput v-model="bank.bank_name" placeholder="Bank Name" :disabled="readonly" fluid />
                            </div>
                            <div class="col-span-12 md:col-span-4 flex flex-col gap-1">
                                <label class="text-[10px] font-bold uppercase text-gray-400">Account Number</label>
                                <BaseInput v-model="bank.account_number" placeholder="Account #" :disabled="readonly" fluid />
                            </div>
                            <div class="col-span-12 md:col-span-4 flex flex-col gap-1">
                                <label class="text-[10px] font-bold uppercase text-gray-400">IFSC Code</label>
                                <BaseInput v-model="bank.ifsc_code" placeholder="IFSC" :disabled="readonly" fluid />
                            </div>

                            <div class="col-span-12 md:col-span-6 flex flex-col gap-1">
                                <label class="text-[10px] font-bold uppercase text-gray-400">Branch Name</label>
                                <BaseInput v-model="bank.bank_branch" placeholder="Branch" :disabled="readonly" fluid />
                            </div>
                            <div class="col-span-12 md:col-span-6 flex flex-col gap-1">
                                <label class="text-[10px] font-bold uppercase text-gray-400">Bank Address</label>
                                <BaseInput v-model="bank.bank_address" placeholder="Bank Location" :disabled="readonly" fluid />
                            </div>
                        </div>
                    </div>

                    <Button
                        v-if="!readonly"
                        label="Add Bank Account"
                        icon="pi pi-plus"
                        text
                        severity="secondary"
                        class="self-start !text-violet-600 hover:!bg-violet-50"
                        @click="addBank"
                    />
                    <div v-if="!form.bank_accounts?.length" class="text-center py-6 text-gray-400 text-sm">
                        <i class="pi pi-credit-card text-2xl mb-2 block opacity-30"></i>
                        No bank accounts added yet.
                    </div>
                </div>
            </TabPanel>

            <!-- Taxes Tab -->
            <TabPanel value="4">
                <div class="flex flex-col gap-3 pt-4">
                    <div
                        v-for="(tax, i) in form.taxes"
                        :key="i"
                        class="relative p-4 border border-gray-200 dark:border-gray-700 rounded-xl bg-gray-50/50 dark:bg-gray-800/30 flex flex-col gap-3"
                    >
                        <div class="flex items-center justify-between mb-1">
                            <span class="text-[10px] font-black uppercase text-gray-400 tracking-widest">Tax Record #{{ i + 1 }}</span>
                            <Button v-if="!readonly" icon="pi pi-trash" severity="danger" text size="small" @click="removeRel(form.taxes, i)" />
                        </div>
                        <div class="grid grid-cols-12 gap-3">
                            <div class="col-span-12 md:col-span-4 flex flex-col gap-1">
                                <label class="text-[10px] font-bold uppercase text-gray-400">Tax Type</label>
                                <BaseSelect v-model="tax.tax_type" :options="taxTypeOptions" placeholder="Select Type" :disabled="readonly" fluid />
                            </div>
                            <div class="col-span-12 md:col-span-5 flex flex-col gap-1">
                                <label class="text-[10px] font-bold uppercase text-gray-400">Tax Number</label>
                                <BaseInput 
                                    v-model="tax.tax_number" 
                                    placeholder="Registration No / ID" 
                                    :disabled="readonly" 
                                    fluid 
                                    @input="tax.tax_number = tax.tax_number.toUpperCase().replace(/[^A-Z0-9]/g, '')"
                                />
                            </div>
                            <div class="col-span-12 md:col-span-3 flex items-center gap-2 pt-6">
                                <ToggleSwitch v-model="tax.is_primary" :trueValue="1" :falseValue="0" :disabled="readonly" />
                                <span class="text-xs font-bold text-gray-500 uppercase">Primary Tax</span>
                            </div>

                            <div class="col-span-12 md:col-span-6 flex flex-col gap-1">
                                <label class="text-[10px] font-bold uppercase text-gray-400">Country</label>
                                <BaseSelect v-model="tax.country_id" :options="countries" optionLabel="country_name" optionValue="id" placeholder="Select Country" :disabled="readonly" fluid />
                            </div>
                            <div class="col-span-12 md:col-span-6 flex flex-col gap-1">
                                <label class="text-[10px] font-bold uppercase text-gray-400">State (Optional)</label>
                                <BaseSelect v-model="tax.state_id" :options="filteredStates(tax.country_id)" optionLabel="state_name" optionValue="id" placeholder="Select State" :disabled="readonly" fluid />
                            </div>
                        </div>
                    </div>

                    <Button
                        v-if="!readonly"
                        label="Add Tax Info"
                        icon="pi pi-plus"
                        text
                        severity="secondary"
                        class="self-start !text-violet-600 hover:!bg-violet-50"
                        @click="addTax"
                    />
                    <div v-if="!form.taxes?.length" class="text-center py-6 text-gray-400 text-sm">
                        <i class="pi pi-percentage text-2xl mb-2 block opacity-30"></i>
                        No tax information added yet.
                    </div>
                </div>
            </TabPanel>
        </TabPanels>
    </Tabs>
</template>
