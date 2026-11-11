<script setup>
import { Head } from '@inertiajs/vue3';
import BaseCard from '@/Components/Base/BaseCard.vue';

const sections = [
    {
        id: 'information', title: '1. What information do we collect?', paragraphs: [
            'We collect personal information that you voluntarily provide when you express interest in our products or services, participate in activities on the Services, or contact us. This may include usernames, email addresses, and passwords.',
            'If you use our mobile application, we may request access to device storage and other device features. You can change permissions in your device settings. We may also send account or application push notifications, which you can disable in your device settings.',
            'Information you provide must be true, complete, and accurate. Please notify us when it changes. We do not process sensitive personal information and do not receive personal information from third parties.'
        ]
    },
    {
        id: 'processing', title: '2. How do we process your information?', paragraphs: [
            'We process information to provide, improve, and administer our Services; respond to questions and provide support; send administrative messages; manage orders and payments; enable user communications; request feedback; protect the Services; evaluate usage and improve our products; and comply with legal obligations.',
            'We may use information for marketing or promotional communications in line with your preferences. You can opt out of marketing emails at any time.'
        ]
    },
    {
        id: 'legal-bases', title: '3. What legal bases do we rely on?', paragraphs: [
            'We process personal information when we have a valid legal reason, such as your consent, to provide services or fulfill a contract, to comply with law, to protect rights, or to pursue legitimate business interests. Where we rely on consent, you may withdraw it at any time. Applicable law may permit processing without consent in limited circumstances.'
        ]
    },
    {
        id: 'sharing', title: '4. When and with whom do we share your information?', paragraphs: [
            'We may share personal information in specific situations, including with a successor in connection with a merger, sale of company assets, financing, or acquisition of all or part of our business.'
        ]
    },
    {
        id: 'retention', title: '5. How long do we keep your information?', paragraphs: [
            'We keep personal information for as long as necessary for the purposes described in this notice unless a longer period is required or permitted by law. When we no longer have a legitimate business need to process it, we delete or anonymize it. If immediate deletion is not possible, for example from backup archives, we securely store it and isolate it from further processing until deletion is possible.'
        ]
    },
    {
        id: 'security', title: '6. How do we keep your information safe?', paragraphs: [
            'We use organizational and technical security measures designed to protect personal information. No internet transmission or storage system can be guaranteed completely secure, so transmission to and from our Services is at your own risk. Access the Services within a secure environment.'
        ]
    },
    {
        id: 'minors', title: '7. Do we collect information from minors?', paragraphs: [
            'We do not knowingly solicit data from or market to children under 18. If we learn that we have collected personal information from a person under 18, we will deactivate the account and take reasonable measures to promptly delete the data. If you believe we may have collected such data, contact sayee@onemodo.com.'
        ]
    },
    {
        id: 'rights', title: '8. What are your privacy rights?', paragraphs: [
            'Depending on your location and applicable law, you may have the right to access and obtain a copy of your information, request correction or erasure, restrict processing, request data portability, object to processing, or challenge certain automated decisions. You may also withdraw consent where processing is based on consent. To exercise your rights, contact us using the details below. We will respond in accordance with applicable data protection laws.'
        ]
    },
    {
        id: 'tracking', title: '9. Controls for do-not-track features', paragraphs: [
            'Some browsers and mobile operating systems offer Do-Not-Track settings. There is no uniform standard for responding to these signals, and we do not currently respond to them. If a standard is adopted that we must follow, we will describe that practice in a revised notice.'
        ]
    },
    {
        id: 'updates', title: '10. Do we make updates to this notice?', paragraphs: [
            'We may update this notice from time to time. The revised date will be updated when changes are made, and the new version will take effect when posted. For material changes, we may post a prominent notice or contact you directly. We encourage you to review this notice periodically.'
        ]
    },
    {
        id: 'contact', title: '11. How can you contact us about this notice?', paragraphs: [
            'If you have questions or comments, email sayee@onemodo.com or write to Onemodo Technologies Pvt. Ltd.'
        ]
    },
    {
        id: 'account-deletion', title: '12. How can you review, update, or delete your data?', paragraphs: [
            'Subject to applicable law, you may request access to, correction of, or deletion of personal information we hold about you. Use the public account deletion request page to request deletion of your MODORMC account and associated personal data. We may retain limited information when required for legal, security, fraud prevention, or accounting purposes; retained information is restricted and kept only for the applicable period.'
        ]
    },
];
</script>

<template>

    <Head title="Privacy Policy" />

    <main
        class="min-h-screen bg-slate-100 px-4 py-10 font-sans text-slate-700 dark:bg-slate-950 dark:text-slate-200 sm:px-6">
        <BaseCard
            :padded="false"
            class="mx-auto max-w-4xl overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xl shadow-slate-900/5 dark:border-slate-800 dark:bg-slate-900">
            <div class="h-1.5 bg-gradient-to-r from-indigo-600 via-indigo-500 to-teal-400"></div>
            <div class="p-6 sm:p-10">
                <div class="mb-4 flex justify-center">
                    <img src="/assets/modormc_logo_v1.0.png" alt="MODORMC" class="h-14 w-auto object-contain" />
                </div>
                <p class="text-center text-xs font-bold uppercase tracking-[0.2em] text-indigo-600 dark:text-indigo-400">
                    MODORMC · Onemodo Technologies</p>
                <h1 class="mt-3 text-center text-3xl font-bold text-slate-900 dark:text-white sm:text-4xl">Privacy
                    Policy</h1>
                <p class="mt-3 text-center text-sm text-slate-500 dark:text-slate-400">Last updated October 30, 2023</p>

                <div
                    class="prose prose-slate mt-8 max-w-none dark:prose-invert prose-headings:font-semibold prose-headings:text-slate-900 dark:prose-headings:text-white prose-a:font-medium prose-a:text-indigo-600 hover:prose-a:text-indigo-700 dark:prose-a:text-indigo-400">
                    <p>This privacy notice for Onemodo Technologies Pvt. Ltd. ("we," "us," or "our") describes how and
                        why we may collect, store, use, and share your information when you use our services
                        ("Services"), including the MODORMC mobile application and other applications that link to this
                        notice, or engage with us in related ways such as sales, marketing, or events. If you do not
                        agree with our policies and practices, please do not use our Services.</p>

                    <h2>Summary of key points</h2>
                    <p>We process information based on how you use our Services and the choices you make. We do not
                        process sensitive personal information or receive personal information from third parties. We
                        use information to provide and improve our Services, communicate with you, protect our Services,
                        and comply with law. We may share information in limited situations, such as a business
                        transfer. We use organizational and technical safeguards, but cannot guarantee complete
                        security. Depending on where you live, you may have rights to access, correct, or delete your
                        information. To exercise those rights, contact us at <a
                            href="mailto:sayee@onemodo.com">sayee@onemodo.com</a>.</p>

                    <h2>Table of contents</h2>
                    <ol>
                        <li v-for="section in sections" :key="section.id"><a :href="`#${section.id}`">{{ section.title
                                }}</a></li>
                    </ol>

                    <section v-for="section in sections" :id="section.id" :key="section.id" class="scroll-mt-6">
                        <h2>{{ section.title }}</h2>
                        <p v-for="paragraph in section.paragraphs" :key="paragraph">{{ paragraph }}</p>
                    </section>

                    <p>For account deletion instructions, visit <a href="/account-deletion">Account Deletion</a>.</p>
                </div>
            </div>
        </BaseCard>
    </main>
</template>
