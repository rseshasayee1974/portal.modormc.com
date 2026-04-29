import{v as P,p as Q,a2 as ge,o as s,d as i,n as k,a as n,h,t as y,z as G,i as H,B as ce,A as f,a3 as he,a4 as be,G as F,a5 as x,$ as ve,c as b,J as I,F as C,a6 as ye,Q as we,H as R,w as p,b as g,a7 as ke,g as D,a8 as z,a9 as U,r as A,e as _,y as xe,L as te,f as j,q as Ce,M as oe}from"./app-B1Xz-Nfc.js";import{A as je}from"./ApplicationMark-BP4xYWEQ.js";import{_ as N,a as M}from"./DropdownLink-DzgMyEc-.js";import{a as Ie,s as Se}from"./index-qw1XeJws.js";import{s as ue,R as _e,a as Y,f as de}from"./index-vVErtHBV.js";import{s as ne}from"./index-DIXrXJQA.js";import{s as re}from"./index-uEfbXvIL.js";import{s as Me}from"./index-CxXdTrK3.js";import{s as Ae}from"./index-DHsFDjk9.js";import{r as $e}from"./ScaleIcon-D-HLvD5R.js";import{r as Pe}from"./DocumentTextIcon-BvHiicIu.js";import{r as Te}from"./ClockIcon-CPY-v_GD.js";import{r as Be}from"./ArrowUpRightIcon-AofJp5QH.js";import{r as Le}from"./BriefcaseIcon-BRnzGlc3.js";import{r as Ee}from"./IdentificationIcon-CSmcvZs8.js";import{r as Oe}from"./ClipboardDocumentListIcon-Bf2QOi79.js";import{r as Ze}from"./Cog6ToothIcon-BCPUYAgO.js";function ze(){const e=Q(),t=P(()=>e.props.user_permissions??[]),o=P(()=>e.props.user_role??""),r=P(()=>o.value==="Super Administrator"||o.value==="Saas Owner");return{can:a=>r.value?!0:t.value.includes(a),isSuperAdmin:r,permissions:t,userRole:o}}const Ve={class:"max-w-screen-xl mx-auto py-2 px-3 sm:px-6 lg:px-8"},De={class:"flex items-center justify-between flex-wrap"},Re={class:"w-0 flex-1 flex items-center min-w-0"},He={key:0,class:"size-5 text-white",xmlns:"http://www.w3.org/2000/svg",fill:"none",viewBox:"0 0 24 24","stroke-width":"1.5",stroke:"currentColor"},Fe={key:1,class:"size-5 text-white",xmlns:"http://www.w3.org/2000/svg",fill:"none",viewBox:"0 0 24 24","stroke-width":"1.5",stroke:"currentColor"},Ue={class:"ms-3 font-medium text-sm text-white truncate"},Ne={class:"shrink-0 sm:ms-3"},Ge={__name:"Banner",setup(e){const t=Q(),o=H(!0),r=H("success"),d=H("");return ge(async()=>{var a,m;r.value=((a=t.props.jetstream.flash)==null?void 0:a.bannerStyle)||"success",d.value=((m=t.props.jetstream.flash)==null?void 0:m.banner)||"",o.value=!0}),(a,m)=>(s(),i("div",null,[o.value&&d.value?(s(),i("div",{key:0,class:k({bgindigo500:r.value=="success",bgred700:r.value=="danger"})},[n("div",Ve,[n("div",De,[n("div",Re,[n("span",{class:k(["flex p-2 rounded-lg",{bgindigo600:r.value=="success",bgred600:r.value=="danger"}])},[r.value=="success"?(s(),i("svg",He,[...m[1]||(m[1]=[n("path",{"stroke-linecap":"round","stroke-linejoin":"round",d:"M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"},null,-1)])])):h("",!0),r.value=="danger"?(s(),i("svg",Fe,[...m[2]||(m[2]=[n("path",{"stroke-linecap":"round","stroke-linejoin":"round",d:"M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"},null,-1)])])):h("",!0)],2),n("p",Ue,y(d.value),1)]),n("div",Ne,[n("button",{type:"button",class:k(["-me-1 flex p-2 rounded-md focus:outline-none sm:-me-2 transition",{"hover:bg-indigo-600 focus:bg-indigo-600":r.value=="success","hover:bg-red-600 focus:bg-red-600":r.value=="danger"}]),"aria-label":"Dismiss",onClick:m[0]||(m[0]=G(S=>o.value=!1,["prevent"]))},[...m[3]||(m[3]=[n("svg",{class:"size-5 text-white",xmlns:"http://www.w3.org/2000/svg",fill:"none",viewBox:"0 0 24 24","stroke-width":"1.5",stroke:"currentColor"},[n("path",{"stroke-linecap":"round","stroke-linejoin":"round",d:"M6 18L18 6M6 6l12 12"})],-1)])],2)])])])],2)):h("",!0)]))}};function We(e,t){return s(),i("svg",{xmlns:"http://www.w3.org/2000/svg",fill:"none",viewBox:"0 0 24 24","stroke-width":"1.5",stroke:"currentColor","aria-hidden":"true","data-slot":"icon"},[n("path",{"stroke-linecap":"round","stroke-linejoin":"round",d:"m19.5 4.5-15 15m0 0h11.25m-11.25 0V8.25"})])}function Ke(e,t){return s(),i("svg",{xmlns:"http://www.w3.org/2000/svg",fill:"none",viewBox:"0 0 24 24","stroke-width":"1.5",stroke:"currentColor","aria-hidden":"true","data-slot":"icon"},[n("path",{"stroke-linecap":"round","stroke-linejoin":"round",d:"M9 8.25H7.5a2.25 2.25 0 0 0-2.25 2.25v9a2.25 2.25 0 0 0 2.25 2.25h9a2.25 2.25 0 0 0 2.25-2.25v-9a2.25 2.25 0 0 0-2.25-2.25H15M9 12l3 3m0 0 3-3m-3 3V2.25"})])}function qe(e,t){return s(),i("svg",{xmlns:"http://www.w3.org/2000/svg",fill:"none",viewBox:"0 0 24 24","stroke-width":"1.5",stroke:"currentColor","aria-hidden":"true","data-slot":"icon"},[n("path",{"stroke-linecap":"round","stroke-linejoin":"round",d:"M9 8.25H7.5a2.25 2.25 0 0 0-2.25 2.25v9a2.25 2.25 0 0 0 2.25 2.25h9a2.25 2.25 0 0 0 2.25-2.25v-9a2.25 2.25 0 0 0-2.25-2.25H15m0-3-3-3m0 0-3 3m3-3V15"})])}function se(e,t){return s(),i("svg",{xmlns:"http://www.w3.org/2000/svg",fill:"none",viewBox:"0 0 24 24","stroke-width":"1.5",stroke:"currentColor","aria-hidden":"true","data-slot":"icon"},[n("path",{"stroke-linecap":"round","stroke-linejoin":"round",d:"M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0"})])}function Xe(e,t){return s(),i("svg",{xmlns:"http://www.w3.org/2000/svg",fill:"none",viewBox:"0 0 24 24","stroke-width":"1.5",stroke:"currentColor","aria-hidden":"true","data-slot":"icon"},[n("path",{"stroke-linecap":"round","stroke-linejoin":"round",d:"M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z"})])}function Je(e,t){return s(),i("svg",{xmlns:"http://www.w3.org/2000/svg",fill:"none",viewBox:"0 0 24 24","stroke-width":"1.5",stroke:"currentColor","aria-hidden":"true","data-slot":"icon"},[n("path",{"stroke-linecap":"round","stroke-linejoin":"round",d:"M10.5 6a7.5 7.5 0 1 0 7.5 7.5h-7.5V6Z"}),n("path",{"stroke-linecap":"round","stroke-linejoin":"round",d:"M13.5 10.5H21A7.5 7.5 0 0 0 13.5 3v7.5Z"})])}function Qe(e,t){return s(),i("svg",{xmlns:"http://www.w3.org/2000/svg",fill:"none",viewBox:"0 0 24 24","stroke-width":"1.5",stroke:"currentColor","aria-hidden":"true","data-slot":"icon"},[n("path",{"stroke-linecap":"round","stroke-linejoin":"round",d:"M4.5 12a7.5 7.5 0 0 0 15 0m-15 0a7.5 7.5 0 1 1 15 0m-15 0H3m16.5 0H21m-1.5 0H12m-8.457 3.077 1.41-.513m14.095-5.13 1.41-.513M5.106 17.785l1.15-.964m11.49-9.642 1.149-.964M7.501 19.795l.75-1.3m7.5-12.99.75-1.3m-6.063 16.658.26-1.477m2.605-14.772.26-1.477m0 17.726-.26-1.477M10.698 4.614l-.26-1.477M16.5 19.794l-.75-1.299M7.5 4.205 12 12m6.894 5.785-1.149-.964M6.256 7.178l-1.15-.964m15.352 8.864-1.41-.513M4.954 9.435l-1.41-.514M12.002 12l-3.75 6.495"})])}function Ye(e,t){return s(),i("svg",{xmlns:"http://www.w3.org/2000/svg",fill:"none",viewBox:"0 0 24 24","stroke-width":"1.5",stroke:"currentColor","aria-hidden":"true","data-slot":"icon"},[n("path",{"stroke-linecap":"round","stroke-linejoin":"round",d:"M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 0 0 2.25-2.25V6.75A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25v10.5A2.25 2.25 0 0 0 4.5 19.5Z"})])}function et(e,t){return s(),i("svg",{xmlns:"http://www.w3.org/2000/svg",fill:"none",viewBox:"0 0 24 24","stroke-width":"1.5",stroke:"currentColor","aria-hidden":"true","data-slot":"icon"},[n("path",{"stroke-linecap":"round","stroke-linejoin":"round",d:"m2.25 12 8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25"})])}function tt(e,t){return s(),i("svg",{xmlns:"http://www.w3.org/2000/svg",fill:"none",viewBox:"0 0 24 24","stroke-width":"1.5",stroke:"currentColor","aria-hidden":"true","data-slot":"icon"},[n("path",{"stroke-linecap":"round","stroke-linejoin":"round",d:"M9.53 16.122a3 3 0 0 0-5.78 1.128 2.25 2.25 0 0 1-2.4 2.245 4.5 4.5 0 0 0 8.4-2.245c0-.399-.078-.78-.22-1.128Zm0 0a15.998 15.998 0 0 0 3.388-1.62m-5.043-.025a15.994 15.994 0 0 1 1.622-3.395m3.42 3.42a15.995 15.995 0 0 0 4.764-4.648l3.876-5.814a1.151 1.151 0 0 0-1.597-1.597L14.146 6.32a15.996 15.996 0 0 0-4.649 4.763m3.42 3.42a6.776 6.776 0 0 0-3.42-3.42"})])}function ot(e,t){return s(),i("svg",{xmlns:"http://www.w3.org/2000/svg",fill:"none",viewBox:"0 0 24 24","stroke-width":"1.5",stroke:"currentColor","aria-hidden":"true","data-slot":"icon"},[n("path",{"stroke-linecap":"round","stroke-linejoin":"round",d:"m9 14.25 6-6m4.5-3.493V21.75l-3.75-1.5-3.75 1.5-3.75-1.5-3.75 1.5V4.757c0-1.108.806-2.057 1.907-2.185a48.507 48.507 0 0 1 11.186 0c1.1.128 1.907 1.077 1.907 2.185ZM9.75 9h.008v.008H9.75V9Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm4.125 4.5h.008v.008h-.008V13.5Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z"})])}function nt(e,t){return s(),i("svg",{xmlns:"http://www.w3.org/2000/svg",fill:"none",viewBox:"0 0 24 24","stroke-width":"1.5",stroke:"currentColor","aria-hidden":"true","data-slot":"icon"},[n("path",{"stroke-linecap":"round","stroke-linejoin":"round",d:"M6.429 9.75 2.25 12l4.179 2.25m0-4.5 5.571 3 5.571-3m-11.142 0L2.25 7.5 12 2.25l9.75 5.25-4.179 2.25m0 0L21.75 12l-4.179 2.25m0 0 4.179 2.25L12 21.75 2.25 16.5l4.179-2.25m11.142 0-5.571 3-5.571-3"})])}function rt(e,t){return s(),i("svg",{xmlns:"http://www.w3.org/2000/svg",fill:"none",viewBox:"0 0 24 24","stroke-width":"1.5",stroke:"currentColor","aria-hidden":"true","data-slot":"icon"},[n("path",{"stroke-linecap":"round","stroke-linejoin":"round",d:"M4.098 19.902a3.75 3.75 0 0 0 5.304 0l6.401-6.402M6.75 21A3.75 3.75 0 0 1 3 17.25V4.125C3 3.504 3.504 3 4.125 3h5.25c.621 0 1.125.504 1.125 1.125v4.072M6.75 21a3.75 3.75 0 0 0 3.75-3.75V8.197M6.75 21h13.125c.621 0 1.125-.504 1.125-1.125v-5.25c0-.621-.504-1.125-1.125-1.125h-4.072M10.5 8.197l2.88-2.88c.438-.439 1.15-.439 1.59 0l3.712 3.713c.44.44.44 1.152 0 1.59l-2.879 2.88M6.75 17.25h.008v.008H6.75v-.008Z"})])}function st(e,t){return s(),i("svg",{xmlns:"http://www.w3.org/2000/svg",fill:"none",viewBox:"0 0 24 24","stroke-width":"1.5",stroke:"currentColor","aria-hidden":"true","data-slot":"icon"},[n("path",{"stroke-linecap":"round","stroke-linejoin":"round",d:"M17.982 18.725A7.488 7.488 0 0 0 12 15.75a7.488 7.488 0 0 0-5.982 2.975m11.963 0a9 9 0 1 0-11.963 0m11.963 0A8.966 8.966 0 0 1 12 21a8.966 8.966 0 0 1-5.982-2.275M15 9.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"})])}function at(e,t){return s(),i("svg",{xmlns:"http://www.w3.org/2000/svg",fill:"none",viewBox:"0 0 24 24","stroke-width":"1.5",stroke:"currentColor","aria-hidden":"true","data-slot":"icon"},[n("path",{"stroke-linecap":"round","stroke-linejoin":"round",d:"M18 7.5v3m0 0v3m0-3h3m-3 0h-3m-2.25-4.125a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0ZM3 19.235v-.11a6.375 6.375 0 0 1 12.75 0v.109A12.318 12.318 0 0 1 9.374 21c-2.331 0-4.512-.645-6.374-1.766Z"})])}var it=`
    .p-toast {
        width: dt('toast.width');
        white-space: pre-line;
        word-break: break-word;
    }

    .p-toast-message {
        margin: 0 0 1rem 0;
        display: grid;
        grid-template-rows: 1fr;
    }

    .p-toast-message-icon {
        flex-shrink: 0;
        font-size: dt('toast.icon.size');
        width: dt('toast.icon.size');
        height: dt('toast.icon.size');
    }

    .p-toast-message-content {
        display: flex;
        align-items: flex-start;
        padding: dt('toast.content.padding');
        gap: dt('toast.content.gap');
        min-height: 0;
        overflow: hidden;
        transition: padding 250ms ease-in;
    }

    .p-toast-message-text {
        flex: 1 1 auto;
        display: flex;
        flex-direction: column;
        gap: dt('toast.text.gap');
    }

    .p-toast-summary {
        font-weight: dt('toast.summary.font.weight');
        font-size: dt('toast.summary.font.size');
    }

    .p-toast-detail {
        font-weight: dt('toast.detail.font.weight');
        font-size: dt('toast.detail.font.size');
    }

    .p-toast-close-button {
        display: flex;
        align-items: center;
        justify-content: center;
        overflow: hidden;
        position: relative;
        cursor: pointer;
        background: transparent;
        transition:
            background dt('toast.transition.duration'),
            color dt('toast.transition.duration'),
            outline-color dt('toast.transition.duration'),
            box-shadow dt('toast.transition.duration');
        outline-color: transparent;
        color: inherit;
        width: dt('toast.close.button.width');
        height: dt('toast.close.button.height');
        border-radius: dt('toast.close.button.border.radius');
        margin: -25% 0 0 0;
        right: -25%;
        padding: 0;
        border: none;
        user-select: none;
    }

    .p-toast-close-button:dir(rtl) {
        margin: -25% 0 0 auto;
        left: -25%;
        right: auto;
    }

    .p-toast-message-info,
    .p-toast-message-success,
    .p-toast-message-warn,
    .p-toast-message-error,
    .p-toast-message-secondary,
    .p-toast-message-contrast {
        border-width: dt('toast.border.width');
        border-style: solid;
        backdrop-filter: blur(dt('toast.blur'));
        border-radius: dt('toast.border.radius');
    }

    .p-toast-close-icon {
        font-size: dt('toast.close.icon.size');
        width: dt('toast.close.icon.size');
        height: dt('toast.close.icon.size');
    }

    .p-toast-close-button:focus-visible {
        outline-width: dt('focus.ring.width');
        outline-style: dt('focus.ring.style');
        outline-offset: dt('focus.ring.offset');
    }

    .p-toast-message-info {
        background: dt('toast.info.background');
        border-color: dt('toast.info.border.color');
        color: dt('toast.info.color');
        box-shadow: dt('toast.info.shadow');
    }

    .p-toast-message-info .p-toast-detail {
        color: dt('toast.info.detail.color');
    }

    .p-toast-message-info .p-toast-close-button:focus-visible {
        outline-color: dt('toast.info.close.button.focus.ring.color');
        box-shadow: dt('toast.info.close.button.focus.ring.shadow');
    }

    .p-toast-message-info .p-toast-close-button:hover {
        background: dt('toast.info.close.button.hover.background');
    }

    .p-toast-message-success {
        background: dt('toast.success.background');
        border-color: dt('toast.success.border.color');
        color: dt('toast.success.color');
        box-shadow: dt('toast.success.shadow');
    }

    .p-toast-message-success .p-toast-detail {
        color: dt('toast.success.detail.color');
    }

    .p-toast-message-success .p-toast-close-button:focus-visible {
        outline-color: dt('toast.success.close.button.focus.ring.color');
        box-shadow: dt('toast.success.close.button.focus.ring.shadow');
    }

    .p-toast-message-success .p-toast-close-button:hover {
        background: dt('toast.success.close.button.hover.background');
    }

    .p-toast-message-warn {
        background: dt('toast.warn.background');
        border-color: dt('toast.warn.border.color');
        color: dt('toast.warn.color');
        box-shadow: dt('toast.warn.shadow');
    }

    .p-toast-message-warn .p-toast-detail {
        color: dt('toast.warn.detail.color');
    }

    .p-toast-message-warn .p-toast-close-button:focus-visible {
        outline-color: dt('toast.warn.close.button.focus.ring.color');
        box-shadow: dt('toast.warn.close.button.focus.ring.shadow');
    }

    .p-toast-message-warn .p-toast-close-button:hover {
        background: dt('toast.warn.close.button.hover.background');
    }

    .p-toast-message-error {
        background: dt('toast.error.background');
        border-color: dt('toast.error.border.color');
        color: dt('toast.error.color');
        box-shadow: dt('toast.error.shadow');
    }

    .p-toast-message-error .p-toast-detail {
        color: dt('toast.error.detail.color');
    }

    .p-toast-message-error .p-toast-close-button:focus-visible {
        outline-color: dt('toast.error.close.button.focus.ring.color');
        box-shadow: dt('toast.error.close.button.focus.ring.shadow');
    }

    .p-toast-message-error .p-toast-close-button:hover {
        background: dt('toast.error.close.button.hover.background');
    }

    .p-toast-message-secondary {
        background: dt('toast.secondary.background');
        border-color: dt('toast.secondary.border.color');
        color: dt('toast.secondary.color');
        box-shadow: dt('toast.secondary.shadow');
    }

    .p-toast-message-secondary .p-toast-detail {
        color: dt('toast.secondary.detail.color');
    }

    .p-toast-message-secondary .p-toast-close-button:focus-visible {
        outline-color: dt('toast.secondary.close.button.focus.ring.color');
        box-shadow: dt('toast.secondary.close.button.focus.ring.shadow');
    }

    .p-toast-message-secondary .p-toast-close-button:hover {
        background: dt('toast.secondary.close.button.hover.background');
    }

    .p-toast-message-contrast {
        background: dt('toast.contrast.background');
        border-color: dt('toast.contrast.border.color');
        color: dt('toast.contrast.color');
        box-shadow: dt('toast.contrast.shadow');
    }
    
    .p-toast-message-contrast .p-toast-detail {
        color: dt('toast.contrast.detail.color');
    }

    .p-toast-message-contrast .p-toast-close-button:focus-visible {
        outline-color: dt('toast.contrast.close.button.focus.ring.color');
        box-shadow: dt('toast.contrast.close.button.focus.ring.shadow');
    }

    .p-toast-message-contrast .p-toast-close-button:hover {
        background: dt('toast.contrast.close.button.hover.background');
    }

    .p-toast-top-center {
        transform: translateX(-50%);
    }

    .p-toast-bottom-center {
        transform: translateX(-50%);
    }

    .p-toast-center {
        min-width: 20vw;
        transform: translate(-50%, -50%);
    }

    .p-toast-message-enter-active {
        animation: p-animate-toast-enter 300ms ease-out;
    }

    .p-toast-message-leave-active {
        animation: p-animate-toast-leave 250ms ease-in;
    }

    .p-toast-message-leave-to .p-toast-message-content {
        padding-top: 0;
        padding-bottom: 0;
    }

    @keyframes p-animate-toast-enter {
        from {
            opacity: 0;
            transform: scale(0.6);
        }
        to {
            opacity: 1;
            grid-template-rows: 1fr;
        }
    }

     @keyframes p-animate-toast-leave {
        from {
            opacity: 1;
        }
        to {
            opacity: 0;
            margin-bottom: 0;
            grid-template-rows: 0fr;
            transform: translateY(-100%) scale(0.6);
        }
    }
`;function T(e){"@babel/helpers - typeof";return T=typeof Symbol=="function"&&typeof Symbol.iterator=="symbol"?function(t){return typeof t}:function(t){return t&&typeof Symbol=="function"&&t.constructor===Symbol&&t!==Symbol.prototype?"symbol":typeof t},T(e)}function V(e,t,o){return(t=lt(t))in e?Object.defineProperty(e,t,{value:o,enumerable:!0,configurable:!0,writable:!0}):e[t]=o,e}function lt(e){var t=ct(e,"string");return T(t)=="symbol"?t:t+""}function ct(e,t){if(T(e)!="object"||!e)return e;var o=e[Symbol.toPrimitive];if(o!==void 0){var r=o.call(e,t);if(T(r)!="object")return r;throw new TypeError("@@toPrimitive must return a primitive value.")}return(t==="string"?String:Number)(e)}var ut={root:function(t){var o=t.position;return{position:"fixed",top:o==="top-right"||o==="top-left"||o==="top-center"?"20px":o==="center"?"50%":null,right:(o==="top-right"||o==="bottom-right")&&"20px",bottom:(o==="bottom-left"||o==="bottom-right"||o==="bottom-center")&&"20px",left:o==="top-left"||o==="bottom-left"?"20px":o==="center"||o==="top-center"||o==="bottom-center"?"50%":null}}},dt={root:function(t){var o=t.props;return["p-toast p-component p-toast-"+o.position]},message:function(t){var o=t.props;return["p-toast-message",{"p-toast-message-info":o.message.severity==="info"||o.message.severity===void 0,"p-toast-message-warn":o.message.severity==="warn","p-toast-message-error":o.message.severity==="error","p-toast-message-success":o.message.severity==="success","p-toast-message-secondary":o.message.severity==="secondary","p-toast-message-contrast":o.message.severity==="contrast"}]},messageContent:"p-toast-message-content",messageIcon:function(t){var o=t.props;return["p-toast-message-icon",V(V(V(V({},o.infoIcon,o.message.severity==="info"),o.warnIcon,o.message.severity==="warn"),o.errorIcon,o.message.severity==="error"),o.successIcon,o.message.severity==="success")]},messageText:"p-toast-message-text",summary:"p-toast-summary",detail:"p-toast-detail",closeButton:"p-toast-close-button",closeIcon:"p-toast-close-icon"},mt=ce.extend({name:"toast",style:it,classes:dt,inlineStyles:ut}),W={name:"ExclamationTriangleIcon",extends:ue};function pt(e){return bt(e)||ht(e)||gt(e)||ft()}function ft(){throw new TypeError(`Invalid attempt to spread non-iterable instance.
In order to be iterable, non-array objects must have a [Symbol.iterator]() method.`)}function gt(e,t){if(e){if(typeof e=="string")return K(e,t);var o={}.toString.call(e).slice(8,-1);return o==="Object"&&e.constructor&&(o=e.constructor.name),o==="Map"||o==="Set"?Array.from(e):o==="Arguments"||/^(?:Ui|I)nt(?:8|16|32)(?:Clamped)?Array$/.test(o)?K(e,t):void 0}}function ht(e){if(typeof Symbol<"u"&&e[Symbol.iterator]!=null||e["@@iterator"]!=null)return Array.from(e)}function bt(e){if(Array.isArray(e))return K(e)}function K(e,t){(t==null||t>e.length)&&(t=e.length);for(var o=0,r=Array(t);o<t;o++)r[o]=e[o];return r}function vt(e,t,o,r,d,a){return s(),i("svg",f({width:"14",height:"14",viewBox:"0 0 14 14",fill:"none",xmlns:"http://www.w3.org/2000/svg"},e.pti()),pt(t[0]||(t[0]=[n("path",{d:"M13.4018 13.1893H0.598161C0.49329 13.189 0.390283 13.1615 0.299143 13.1097C0.208003 13.0578 0.131826 12.9832 0.0780112 12.8932C0.0268539 12.8015 0 12.6982 0 12.5931C0 12.4881 0.0268539 12.3848 0.0780112 12.293L6.47985 1.08982C6.53679 1.00399 6.61408 0.933574 6.70484 0.884867C6.7956 0.836159 6.897 0.810669 7 0.810669C7.103 0.810669 7.2044 0.836159 7.29516 0.884867C7.38592 0.933574 7.46321 1.00399 7.52015 1.08982L13.922 12.293C13.9731 12.3848 14 12.4881 14 12.5931C14 12.6982 13.9731 12.8015 13.922 12.8932C13.8682 12.9832 13.792 13.0578 13.7009 13.1097C13.6097 13.1615 13.5067 13.189 13.4018 13.1893ZM1.63046 11.989H12.3695L7 2.59425L1.63046 11.989Z",fill:"currentColor"},null,-1),n("path",{d:"M6.99996 8.78801C6.84143 8.78594 6.68997 8.72204 6.57787 8.60993C6.46576 8.49782 6.40186 8.34637 6.39979 8.18784V5.38703C6.39979 5.22786 6.46302 5.0752 6.57557 4.96265C6.68813 4.85009 6.84078 4.78686 6.99996 4.78686C7.15914 4.78686 7.31179 4.85009 7.42435 4.96265C7.5369 5.0752 7.60013 5.22786 7.60013 5.38703V8.18784C7.59806 8.34637 7.53416 8.49782 7.42205 8.60993C7.30995 8.72204 7.15849 8.78594 6.99996 8.78801Z",fill:"currentColor"},null,-1),n("path",{d:"M6.99996 11.1887C6.84143 11.1866 6.68997 11.1227 6.57787 11.0106C6.46576 10.8985 6.40186 10.7471 6.39979 10.5885V10.1884C6.39979 10.0292 6.46302 9.87658 6.57557 9.76403C6.68813 9.65147 6.84078 9.58824 6.99996 9.58824C7.15914 9.58824 7.31179 9.65147 7.42435 9.76403C7.5369 9.87658 7.60013 10.0292 7.60013 10.1884V10.5885C7.59806 10.7471 7.53416 10.8985 7.42205 11.0106C7.30995 11.1227 7.15849 11.1866 6.99996 11.1887Z",fill:"currentColor"},null,-1)])),16)}W.render=vt;var q={name:"InfoCircleIcon",extends:ue};function yt(e){return Ct(e)||xt(e)||kt(e)||wt()}function wt(){throw new TypeError(`Invalid attempt to spread non-iterable instance.
In order to be iterable, non-array objects must have a [Symbol.iterator]() method.`)}function kt(e,t){if(e){if(typeof e=="string")return X(e,t);var o={}.toString.call(e).slice(8,-1);return o==="Object"&&e.constructor&&(o=e.constructor.name),o==="Map"||o==="Set"?Array.from(e):o==="Arguments"||/^(?:Ui|I)nt(?:8|16|32)(?:Clamped)?Array$/.test(o)?X(e,t):void 0}}function xt(e){if(typeof Symbol<"u"&&e[Symbol.iterator]!=null||e["@@iterator"]!=null)return Array.from(e)}function Ct(e){if(Array.isArray(e))return X(e)}function X(e,t){(t==null||t>e.length)&&(t=e.length);for(var o=0,r=Array(t);o<t;o++)r[o]=e[o];return r}function jt(e,t,o,r,d,a){return s(),i("svg",f({width:"14",height:"14",viewBox:"0 0 14 14",fill:"none",xmlns:"http://www.w3.org/2000/svg"},e.pti()),yt(t[0]||(t[0]=[n("path",{"fill-rule":"evenodd","clip-rule":"evenodd",d:"M3.11101 12.8203C4.26215 13.5895 5.61553 14 7 14C8.85652 14 10.637 13.2625 11.9497 11.9497C13.2625 10.637 14 8.85652 14 7C14 5.61553 13.5895 4.26215 12.8203 3.11101C12.0511 1.95987 10.9579 1.06266 9.67879 0.532846C8.3997 0.00303296 6.99224 -0.13559 5.63437 0.134506C4.2765 0.404603 3.02922 1.07129 2.05026 2.05026C1.07129 3.02922 0.404603 4.2765 0.134506 5.63437C-0.13559 6.99224 0.00303296 8.3997 0.532846 9.67879C1.06266 10.9579 1.95987 12.0511 3.11101 12.8203ZM3.75918 2.14976C4.71846 1.50879 5.84628 1.16667 7 1.16667C8.5471 1.16667 10.0308 1.78125 11.1248 2.87521C12.2188 3.96918 12.8333 5.45291 12.8333 7C12.8333 8.15373 12.4912 9.28154 11.8502 10.2408C11.2093 11.2001 10.2982 11.9478 9.23232 12.3893C8.16642 12.8308 6.99353 12.9463 5.86198 12.7212C4.73042 12.4962 3.69102 11.9406 2.87521 11.1248C2.05941 10.309 1.50384 9.26958 1.27876 8.13803C1.05367 7.00647 1.16919 5.83358 1.61071 4.76768C2.05222 3.70178 2.79989 2.79074 3.75918 2.14976ZM7.00002 4.8611C6.84594 4.85908 6.69873 4.79698 6.58977 4.68801C6.48081 4.57905 6.4187 4.43185 6.41669 4.27776V3.88888C6.41669 3.73417 6.47815 3.58579 6.58754 3.4764C6.69694 3.367 6.84531 3.30554 7.00002 3.30554C7.15473 3.30554 7.3031 3.367 7.4125 3.4764C7.52189 3.58579 7.58335 3.73417 7.58335 3.88888V4.27776C7.58134 4.43185 7.51923 4.57905 7.41027 4.68801C7.30131 4.79698 7.1541 4.85908 7.00002 4.8611ZM7.00002 10.6945C6.84594 10.6925 6.69873 10.6304 6.58977 10.5214C6.48081 10.4124 6.4187 10.2652 6.41669 10.1111V6.22225C6.41669 6.06754 6.47815 5.91917 6.58754 5.80977C6.69694 5.70037 6.84531 5.63892 7.00002 5.63892C7.15473 5.63892 7.3031 5.70037 7.4125 5.80977C7.52189 5.91917 7.58335 6.06754 7.58335 6.22225V10.1111C7.58134 10.2652 7.51923 10.4124 7.41027 10.5214C7.30131 10.6304 7.1541 10.6925 7.00002 10.6945Z",fill:"currentColor"},null,-1)])),16)}q.render=jt;var It={name:"BaseToast",extends:Y,props:{group:{type:String,default:null},position:{type:String,default:"top-right"},autoZIndex:{type:Boolean,default:!0},baseZIndex:{type:Number,default:0},breakpoints:{type:Object,default:null},closeIcon:{type:String,default:void 0},infoIcon:{type:String,default:void 0},warnIcon:{type:String,default:void 0},errorIcon:{type:String,default:void 0},successIcon:{type:String,default:void 0},closeButtonProps:{type:null,default:null},onMouseEnter:{type:Function,default:void 0},onMouseLeave:{type:Function,default:void 0},onClick:{type:Function,default:void 0}},style:mt,provide:function(){return{$pcToast:this,$parentInstance:this}}};function B(e){"@babel/helpers - typeof";return B=typeof Symbol=="function"&&typeof Symbol.iterator=="symbol"?function(t){return typeof t}:function(t){return t&&typeof Symbol=="function"&&t.constructor===Symbol&&t!==Symbol.prototype?"symbol":typeof t},B(e)}function St(e,t,o){return(t=_t(t))in e?Object.defineProperty(e,t,{value:o,enumerable:!0,configurable:!0,writable:!0}):e[t]=o,e}function _t(e){var t=Mt(e,"string");return B(t)=="symbol"?t:t+""}function Mt(e,t){if(B(e)!="object"||!e)return e;var o=e[Symbol.toPrimitive];if(o!==void 0){var r=o.call(e,t);if(B(r)!="object")return r;throw new TypeError("@@toPrimitive must return a primitive value.")}return(t==="string"?String:Number)(e)}var me={name:"ToastMessage",hostName:"Toast",extends:Y,emits:["close"],closeTimeout:null,createdAt:null,lifeRemaining:null,props:{message:{type:null,default:null},templates:{type:Object,default:null},closeIcon:{type:String,default:null},infoIcon:{type:String,default:null},warnIcon:{type:String,default:null},errorIcon:{type:String,default:null},successIcon:{type:String,default:null},closeButtonProps:{type:null,default:null},onMouseEnter:{type:Function,default:void 0},onMouseLeave:{type:Function,default:void 0},onClick:{type:Function,default:void 0}},mounted:function(){this.message.life&&(this.lifeRemaining=this.message.life,this.startTimeout())},beforeUnmount:function(){this.clearCloseTimeout()},methods:{startTimeout:function(){var t=this;this.createdAt=new Date().valueOf(),this.closeTimeout=setTimeout(function(){t.close({message:t.message,type:"life-end"})},this.lifeRemaining)},close:function(t){this.$emit("close",t)},onCloseClick:function(){this.clearCloseTimeout(),this.close({message:this.message,type:"close"})},clearCloseTimeout:function(){this.closeTimeout&&(clearTimeout(this.closeTimeout),this.closeTimeout=null)},onMessageClick:function(t){var o;(o=this.onClick)===null||o===void 0||o.call(this,{originalEvent:t,message:this.message})},handleMouseEnter:function(t){if(this.onMouseEnter){if(this.onMouseEnter({originalEvent:t,message:this.message}),t.defaultPrevented)return;this.message.life&&(this.lifeRemaining=this.createdAt+this.lifeRemaining-new Date().valueOf(),this.createdAt=null,this.clearCloseTimeout())}},handleMouseLeave:function(t){if(this.onMouseLeave){if(this.onMouseLeave({originalEvent:t,message:this.message}),t.defaultPrevented)return;this.message.life&&this.startTimeout()}}},computed:{iconComponent:function(){return{info:!this.infoIcon&&q,success:!this.successIcon&&ne,warn:!this.warnIcon&&W,error:!this.errorIcon&&re}[this.message.severity]},closeAriaLabel:function(){return this.$primevue.config.locale.aria?this.$primevue.config.locale.aria.close:void 0},dataP:function(){return de(St({},this.message.severity,this.message.severity))}},components:{TimesIcon:Se,InfoCircleIcon:q,CheckIcon:ne,ExclamationTriangleIcon:W,TimesCircleIcon:re},directives:{ripple:_e}};function L(e){"@babel/helpers - typeof";return L=typeof Symbol=="function"&&typeof Symbol.iterator=="symbol"?function(t){return typeof t}:function(t){return t&&typeof Symbol=="function"&&t.constructor===Symbol&&t!==Symbol.prototype?"symbol":typeof t},L(e)}function ae(e,t){var o=Object.keys(e);if(Object.getOwnPropertySymbols){var r=Object.getOwnPropertySymbols(e);t&&(r=r.filter(function(d){return Object.getOwnPropertyDescriptor(e,d).enumerable})),o.push.apply(o,r)}return o}function ie(e){for(var t=1;t<arguments.length;t++){var o=arguments[t]!=null?arguments[t]:{};t%2?ae(Object(o),!0).forEach(function(r){At(e,r,o[r])}):Object.getOwnPropertyDescriptors?Object.defineProperties(e,Object.getOwnPropertyDescriptors(o)):ae(Object(o)).forEach(function(r){Object.defineProperty(e,r,Object.getOwnPropertyDescriptor(o,r))})}return e}function At(e,t,o){return(t=$t(t))in e?Object.defineProperty(e,t,{value:o,enumerable:!0,configurable:!0,writable:!0}):e[t]=o,e}function $t(e){var t=Pt(e,"string");return L(t)=="symbol"?t:t+""}function Pt(e,t){if(L(e)!="object"||!e)return e;var o=e[Symbol.toPrimitive];if(o!==void 0){var r=o.call(e,t);if(L(r)!="object")return r;throw new TypeError("@@toPrimitive must return a primitive value.")}return(t==="string"?String:Number)(e)}var Tt=["data-p"],Bt=["data-p"],Lt=["data-p"],Et=["data-p"],Ot=["aria-label","data-p"];function Zt(e,t,o,r,d,a){var m=ve("ripple");return s(),i("div",f({class:[e.cx("message"),o.message.styleClass],role:"alert","aria-live":"assertive","aria-atomic":"true","data-p":a.dataP},e.ptm("message"),{onClick:t[1]||(t[1]=function(){return a.onMessageClick&&a.onMessageClick.apply(a,arguments)}),onMouseenter:t[2]||(t[2]=function(){return a.handleMouseEnter&&a.handleMouseEnter.apply(a,arguments)}),onMouseleave:t[3]||(t[3]=function(){return a.handleMouseLeave&&a.handleMouseLeave.apply(a,arguments)})}),[o.templates.container?(s(),b(I(o.templates.container),{key:0,message:o.message,closeCallback:a.onCloseClick},null,8,["message","closeCallback"])):(s(),i("div",f({key:1,class:[e.cx("messageContent"),o.message.contentStyleClass]},e.ptm("messageContent")),[o.templates.message?(s(),b(I(o.templates.message),{key:1,message:o.message},null,8,["message"])):(s(),i(C,{key:0},[(s(),b(I(o.templates.messageicon?o.templates.messageicon:o.templates.icon?o.templates.icon:a.iconComponent&&a.iconComponent.name?a.iconComponent:"span"),f({class:e.cx("messageIcon")},e.ptm("messageIcon")),null,16,["class"])),n("div",f({class:e.cx("messageText"),"data-p":a.dataP},e.ptm("messageText")),[n("span",f({class:e.cx("summary"),"data-p":a.dataP},e.ptm("summary")),y(o.message.summary),17,Lt),o.message.detail?(s(),i("div",f({key:0,class:e.cx("detail"),"data-p":a.dataP},e.ptm("detail")),y(o.message.detail),17,Et)):h("",!0)],16,Bt)],64)),o.message.closable!==!1?(s(),i("div",ye(f({key:2},e.ptm("buttonContainer"))),[we((s(),i("button",f({class:e.cx("closeButton"),type:"button","aria-label":a.closeAriaLabel,onClick:t[0]||(t[0]=function(){return a.onCloseClick&&a.onCloseClick.apply(a,arguments)}),autofocus:"","data-p":a.dataP},ie(ie({},o.closeButtonProps),e.ptm("closeButton"))),[(s(),b(I(o.templates.closeicon||"TimesIcon"),f({class:[e.cx("closeIcon"),o.closeIcon]},e.ptm("closeIcon")),null,16,["class"]))],16,Ot)),[[m]])],16)):h("",!0)],16))],16,Tt)}me.render=Zt;function E(e){"@babel/helpers - typeof";return E=typeof Symbol=="function"&&typeof Symbol.iterator=="symbol"?function(t){return typeof t}:function(t){return t&&typeof Symbol=="function"&&t.constructor===Symbol&&t!==Symbol.prototype?"symbol":typeof t},E(e)}function zt(e,t,o){return(t=Vt(t))in e?Object.defineProperty(e,t,{value:o,enumerable:!0,configurable:!0,writable:!0}):e[t]=o,e}function Vt(e){var t=Dt(e,"string");return E(t)=="symbol"?t:t+""}function Dt(e,t){if(E(e)!="object"||!e)return e;var o=e[Symbol.toPrimitive];if(o!==void 0){var r=o.call(e,t);if(E(r)!="object")return r;throw new TypeError("@@toPrimitive must return a primitive value.")}return(t==="string"?String:Number)(e)}function Rt(e){return Nt(e)||Ut(e)||Ft(e)||Ht()}function Ht(){throw new TypeError(`Invalid attempt to spread non-iterable instance.
In order to be iterable, non-array objects must have a [Symbol.iterator]() method.`)}function Ft(e,t){if(e){if(typeof e=="string")return J(e,t);var o={}.toString.call(e).slice(8,-1);return o==="Object"&&e.constructor&&(o=e.constructor.name),o==="Map"||o==="Set"?Array.from(e):o==="Arguments"||/^(?:Ui|I)nt(?:8|16|32)(?:Clamped)?Array$/.test(o)?J(e,t):void 0}}function Ut(e){if(typeof Symbol<"u"&&e[Symbol.iterator]!=null||e["@@iterator"]!=null)return Array.from(e)}function Nt(e){if(Array.isArray(e))return J(e)}function J(e,t){(t==null||t>e.length)&&(t=e.length);for(var o=0,r=Array(t);o<t;o++)r[o]=e[o];return r}var Gt=0,pe={name:"Toast",extends:It,inheritAttrs:!1,emits:["close","life-end"],data:function(){return{messages:[]}},styleElement:null,mounted:function(){x.on("add",this.onAdd),x.on("remove",this.onRemove),x.on("remove-group",this.onRemoveGroup),x.on("remove-all-groups",this.onRemoveAllGroups),this.breakpoints&&this.createStyle()},beforeUnmount:function(){this.destroyStyle(),this.$refs.container&&this.autoZIndex&&F.clear(this.$refs.container),x.off("add",this.onAdd),x.off("remove",this.onRemove),x.off("remove-group",this.onRemoveGroup),x.off("remove-all-groups",this.onRemoveAllGroups)},methods:{add:function(t){t.id==null&&(t.id=Gt++),this.messages=[].concat(Rt(this.messages),[t])},remove:function(t){var o=this.messages.findIndex(function(r){return r.id===t.message.id});o!==-1&&(this.messages.splice(o,1),this.$emit(t.type,{message:t.message}))},onAdd:function(t){this.group==t.group&&this.add(t)},onRemove:function(t){this.remove({message:t,type:"close"})},onRemoveGroup:function(t){this.group===t&&(this.messages=[])},onRemoveAllGroups:function(){var t=this;this.messages.forEach(function(o){return t.$emit("close",{message:o})}),this.messages=[]},onEnter:function(){this.autoZIndex&&F.set("modal",this.$refs.container,this.baseZIndex||this.$primevue.config.zIndex.modal)},onLeave:function(){var t=this;this.$refs.container&&this.autoZIndex&&be(this.messages)&&setTimeout(function(){F.clear(t.$refs.container)},200)},createStyle:function(){if(!this.styleElement&&!this.isUnstyled){var t;this.styleElement=document.createElement("style"),this.styleElement.type="text/css",he(this.styleElement,"nonce",(t=this.$primevue)===null||t===void 0||(t=t.config)===null||t===void 0||(t=t.csp)===null||t===void 0?void 0:t.nonce),document.head.appendChild(this.styleElement);var o="";for(var r in this.breakpoints){var d="";for(var a in this.breakpoints[r])d+=a+":"+this.breakpoints[r][a]+"!important;";o+=`
                        @media screen and (max-width: `.concat(r,`) {
                            .p-toast[`).concat(this.$attrSelector,`] {
                                `).concat(d,`
                            }
                        }
                    `)}this.styleElement.innerHTML=o}},destroyStyle:function(){this.styleElement&&(document.head.removeChild(this.styleElement),this.styleElement=null)}},computed:{dataP:function(){return de(zt({},this.position,this.position))}},components:{ToastMessage:me,Portal:Ie}};function O(e){"@babel/helpers - typeof";return O=typeof Symbol=="function"&&typeof Symbol.iterator=="symbol"?function(t){return typeof t}:function(t){return t&&typeof Symbol=="function"&&t.constructor===Symbol&&t!==Symbol.prototype?"symbol":typeof t},O(e)}function le(e,t){var o=Object.keys(e);if(Object.getOwnPropertySymbols){var r=Object.getOwnPropertySymbols(e);t&&(r=r.filter(function(d){return Object.getOwnPropertyDescriptor(e,d).enumerable})),o.push.apply(o,r)}return o}function Wt(e){for(var t=1;t<arguments.length;t++){var o=arguments[t]!=null?arguments[t]:{};t%2?le(Object(o),!0).forEach(function(r){Kt(e,r,o[r])}):Object.getOwnPropertyDescriptors?Object.defineProperties(e,Object.getOwnPropertyDescriptors(o)):le(Object(o)).forEach(function(r){Object.defineProperty(e,r,Object.getOwnPropertyDescriptor(o,r))})}return e}function Kt(e,t,o){return(t=qt(t))in e?Object.defineProperty(e,t,{value:o,enumerable:!0,configurable:!0,writable:!0}):e[t]=o,e}function qt(e){var t=Xt(e,"string");return O(t)=="symbol"?t:t+""}function Xt(e,t){if(O(e)!="object"||!e)return e;var o=e[Symbol.toPrimitive];if(o!==void 0){var r=o.call(e,t);if(O(r)!="object")return r;throw new TypeError("@@toPrimitive must return a primitive value.")}return(t==="string"?String:Number)(e)}var Jt=["data-p"];function Qt(e,t,o,r,d,a){var m=R("ToastMessage"),S=R("Portal");return s(),b(S,null,{default:p(function(){return[n("div",f({ref:"container",class:e.cx("root"),style:e.sx("root",!0,{position:e.position}),"data-p":a.dataP},e.ptmi("root")),[g(ke,f({name:"p-toast-message",tag:"div",onEnter:a.onEnter,onLeave:a.onLeave},Wt({},e.ptm("transition"))),{default:p(function(){return[(s(!0),i(C,null,D(d.messages,function(v){return s(),b(m,{key:v.id,message:v,templates:e.$slots,closeIcon:e.closeIcon,infoIcon:e.infoIcon,warnIcon:e.warnIcon,errorIcon:e.errorIcon,successIcon:e.successIcon,closeButtonProps:e.closeButtonProps,onMouseEnter:e.onMouseEnter,onMouseLeave:e.onMouseLeave,onClick:e.onClick,unstyled:e.unstyled,onClose:t[0]||(t[0]=function(w){return a.remove(w)}),pt:e.pt},null,8,["message","templates","closeIcon","infoIcon","warnIcon","errorIcon","successIcon","closeButtonProps","onMouseEnter","onMouseLeave","onClick","unstyled","pt"])}),128))]}),_:1},16,["onEnter","onLeave"])],16,Jt)]}),_:1})}pe.render=Qt;var Yt=`
    .p-confirmdialog .p-dialog-content {
        display: flex;
        align-items: center;
        gap: dt('confirmdialog.content.gap');
    }

    .p-confirmdialog-icon {
        color: dt('confirmdialog.icon.color');
        font-size: dt('confirmdialog.icon.size');
        width: dt('confirmdialog.icon.size');
        height: dt('confirmdialog.icon.size');
    }
`,eo={root:"p-confirmdialog",icon:"p-confirmdialog-icon",message:"p-confirmdialog-message",pcRejectButton:"p-confirmdialog-reject-button",pcAcceptButton:"p-confirmdialog-accept-button"},to=ce.extend({name:"confirmdialog",style:Yt,classes:eo}),oo={name:"BaseConfirmDialog",extends:Y,props:{group:String,breakpoints:{type:Object,default:null},draggable:{type:Boolean,default:!0}},style:to,provide:function(){return{$pcConfirmDialog:this,$parentInstance:this}}},fe={name:"ConfirmDialog",extends:oo,confirmListener:null,closeListener:null,data:function(){return{visible:!1,confirmation:null}},mounted:function(){var t=this;this.confirmListener=function(o){o&&o.group===t.group&&(t.confirmation=o,t.confirmation.onShow&&t.confirmation.onShow(),t.visible=!0)},this.closeListener=function(){t.visible=!1,t.confirmation=null},z.on("confirm",this.confirmListener),z.on("close",this.closeListener)},beforeUnmount:function(){z.off("confirm",this.confirmListener),z.off("close",this.closeListener)},methods:{accept:function(){this.confirmation.accept&&this.confirmation.accept(),this.visible=!1},reject:function(){this.confirmation.reject&&this.confirmation.reject(),this.visible=!1},onHide:function(){this.confirmation.onHide&&this.confirmation.onHide(),this.visible=!1}},computed:{appendTo:function(){return this.confirmation?this.confirmation.appendTo:"body"},target:function(){return this.confirmation?this.confirmation.target:null},modal:function(){return this.confirmation?this.confirmation.modal==null?!0:this.confirmation.modal:!0},header:function(){return this.confirmation?this.confirmation.header:null},message:function(){return this.confirmation?this.confirmation.message:null},blockScroll:function(){return this.confirmation?this.confirmation.blockScroll:!0},position:function(){return this.confirmation?this.confirmation.position:null},acceptLabel:function(){if(this.confirmation){var t,o=this.confirmation;return o.acceptLabel||((t=o.acceptProps)===null||t===void 0?void 0:t.label)||this.$primevue.config.locale.accept}return this.$primevue.config.locale.accept},rejectLabel:function(){if(this.confirmation){var t,o=this.confirmation;return o.rejectLabel||((t=o.rejectProps)===null||t===void 0?void 0:t.label)||this.$primevue.config.locale.reject}return this.$primevue.config.locale.reject},acceptIcon:function(){var t;return this.confirmation?this.confirmation.acceptIcon:(t=this.confirmation)!==null&&t!==void 0&&t.acceptProps?this.confirmation.acceptProps.icon:null},rejectIcon:function(){var t;return this.confirmation?this.confirmation.rejectIcon:(t=this.confirmation)!==null&&t!==void 0&&t.rejectProps?this.confirmation.rejectProps.icon:null},autoFocusAccept:function(){return this.confirmation.defaultFocus===void 0||this.confirmation.defaultFocus==="accept"},autoFocusReject:function(){return this.confirmation.defaultFocus==="reject"},closeOnEscape:function(){return this.confirmation?this.confirmation.closeOnEscape:!0}},components:{Dialog:Ae,Button:Me}};function no(e,t,o,r,d,a){var m=R("Button"),S=R("Dialog");return s(),b(S,{visible:d.visible,"onUpdate:visible":[t[2]||(t[2]=function(v){return d.visible=v}),a.onHide],role:"alertdialog",class:k(e.cx("root")),modal:a.modal,header:a.header,blockScroll:a.blockScroll,appendTo:a.appendTo,position:a.position,breakpoints:e.breakpoints,closeOnEscape:a.closeOnEscape,draggable:e.draggable,pt:e.pt,unstyled:e.unstyled},U({default:p(function(){return[e.$slots.container?h("",!0):(s(),i(C,{key:0},[e.$slots.message?(s(),b(I(e.$slots.message),{key:1,message:d.confirmation},null,8,["message"])):(s(),i(C,{key:0},[A(e.$slots,"icon",{},function(){return[e.$slots.icon?(s(),b(I(e.$slots.icon),{key:0,class:k(e.cx("icon"))},null,8,["class"])):d.confirmation.icon?(s(),i("span",f({key:1,class:[d.confirmation.icon,e.cx("icon")]},e.ptm("icon")),null,16)):h("",!0)]}),n("span",f({class:e.cx("message")},e.ptm("message")),y(a.message),17)],64))],64))]}),_:2},[e.$slots.container?{name:"container",fn:p(function(v){return[A(e.$slots,"container",{message:d.confirmation,closeCallback:v.closeCallback,acceptCallback:a.accept,rejectCallback:a.reject,initDragCallback:v.initDragCallback})]}),key:"0"}:void 0,e.$slots.container?void 0:{name:"footer",fn:p(function(){var v;return[g(m,f({class:[e.cx("pcRejectButton"),d.confirmation.rejectClass],autofocus:a.autoFocusReject,unstyled:e.unstyled,text:((v=d.confirmation.rejectProps)===null||v===void 0?void 0:v.text)||!1,onClick:t[0]||(t[0]=function(w){return a.reject()})},d.confirmation.rejectProps,{label:a.rejectLabel,pt:e.ptm("pcRejectButton")}),U({_:2},[a.rejectIcon||e.$slots.rejecticon?{name:"icon",fn:p(function(w){return[A(e.$slots,"rejecticon",{},function(){return[n("span",f({class:[a.rejectIcon,w.class]},e.ptm("pcRejectButton").icon,{"data-pc-section":"rejectbuttonicon"}),null,16)]})]}),key:"0"}:void 0]),1040,["class","autofocus","unstyled","text","label","pt"]),g(m,f({label:a.acceptLabel,class:[e.cx("pcAcceptButton"),d.confirmation.acceptClass],autofocus:a.autoFocusAccept,unstyled:e.unstyled,onClick:t[1]||(t[1]=function(w){return a.accept()})},d.confirmation.acceptProps,{pt:e.ptm("pcAcceptButton")}),U({_:2},[a.acceptIcon||e.$slots.accepticon?{name:"icon",fn:p(function(w){return[A(e.$slots,"accepticon",{},function(){return[n("span",f({class:[a.acceptIcon,w.class]},e.ptm("pcAcceptButton").icon,{"data-pc-section":"acceptbuttonicon"}),null,16)]})]}),key:"0"}:void 0]),1040,["label","class","autofocus","unstyled","pt"])]}),key:"1"}]),1032,["visible","class","modal","header","blockScroll","appendTo","position","breakpoints","closeOnEscape","draggable","onUpdate:visible","pt","unstyled"])}fe.render=no;const ro={class:"min-h-screen bg-gray-100 dark:bg-gray-900"},so={class:"bg-customBlue-600 dark:bg-customBlue-800 border-b border-customBlue-800 w-full z-50 shadow-md"},ao={class:"w-full px-2 sm:px-4 lg:px-8"},io={class:"flex items-center justify-between h-16 md:h-20"},lo={class:"hidden md:flex w-52 shrink-0 items-center mr-2 pl-2"},co=["src"],uo={class:"flex-1 overflow-x-auto no-scrollbar"},mo={class:"flex items-stretch justify-evenly min-w-max md:min-w-0 h-full"},po={class:"flex items-center shrink-0 ml-2"},fo={key:0,class:"ms-2 relative"},go={type:"button",class:"inline-flex items-center gap-2 px-2 py-1 rounded-lg text-xs font-medium border border-blue-400/40 bg-white/10 text-blue-100 hover:border-amber-400/60 hover:text-amber-300 transition-all duration-150 focus:outline-none"},ho={class:"max-w-[100px] truncate hidden sm:block"},bo={class:"w-64 py-1"},vo={class:"max-h-[250px] overflow-y-auto"},yo=["onClick"],wo={class:"w-8 h-8 rounded-lg bg-gray-100 dark:bg-gray-700 flex items-center justify-center flex-shrink-0 overflow-hidden"},ko=["src"],xo={key:1,class:"text-sm font-bold text-gray-500"},Co={class:"flex-1 min-w-0"},jo={class:"font-medium truncate"},Io={class:"text-xs text-gray-400 truncate"},So={key:0,class:"w-4 h-4 text-pastelbeaver-500 flex-shrink-0",fill:"none",viewBox:"0 0 24 24","stroke-width":"2",stroke:"currentColor"},_o={class:"relative p-2 text-blue-200 hover:text-amber-300 transition-colors mr-1 sm:mr-2 focus:outline-none rounded-full"},Mo={class:"ms-3 relative"},Ao={class:"inline-flex rounded-md"},$o={type:"button",class:"inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-gray-500 dark:text-gray-400 bg-white dark:bg-gray-800 hover:text-gray-700 dark:hover:text-gray-300 focus:outline-none focus:bg-gray-50 dark:focus:bg-gray-700 active:bg-gray-50 dark:active:bg-gray-700 transition ease-in-out duration-150"},Po=["src","alt"],To={key:1,class:"me-2 h-5 w-5 text-gray-400",xmlns:"http://www.w3.org/2000/svg",fill:"none",viewBox:"0 0 24 24","stroke-width":"1.5",stroke:"currentColor"},Bo={class:"w-60"},Lo=["onSubmit"],Eo={class:"flex items-center"},Oo={key:0,class:"me-2 size-5 text-green-400",xmlns:"http://www.w3.org/2000/svg",fill:"none",viewBox:"0 0 24 24","stroke-width":"1.5",stroke:"currentColor"},Zo={class:"ms-3 relative"},zo={key:0,class:"flex items-center gap-1.5 text-sm border-2 border-blue-400/30 rounded-full focus:outline-none hover:border-amber-400/70 transition-all duration-200"},Vo=["src","alt"],Do={key:1,class:"inline-flex rounded-md"},Ro={type:"button",class:"inline-flex items-center px-3 py-1.5 text-xs font-medium rounded-lg bg-white/10 text-blue-100 hover:bg-white/20 hover:text-amber-300 focus:outline-none transition ease-in-out duration-150"},Ho={key:0,class:"dark:bg-gray-800"},Fo={class:"max-w-7xl mx-auto"},Uo={class:"max-w-7xl mx-auto sm:px-6 lg:px-8"},cn={__name:"AppLayout",props:{title:String},setup(e){const t={HomeIcon:et,Square3Stack3DIcon:nt,CreditCardIcon:Ye,Cog6ToothIcon:Ze,SwatchIcon:rt,ClipboardDocumentListIcon:Oe,IdentificationIcon:Ee,BriefcaseIcon:Le,ChartBarIcon:Xe,BellIcon:se,UserCircleIcon:st,ArrowUpRightIcon:Be,ArrowDownLeftIcon:We,ClockIcon:Te,DocumentTextIcon:Pe,ArrowDownOnSquareIcon:Ke,ArrowUpOnSquareIcon:qe,ChartPieIcon:Je,CogIcon:Qe,UserPlusIcon:at,ReceiptPercentIcon:ot,ScaleIcon:$e,PaintBrushIcon:tt},o=Q(),{can:r}=ze(),d=P(()=>o.props.active_entity),a=P(()=>{var l;return(((l=o.props.menus)==null?void 0:l.top_nav)||[]).filter(u=>!u.permission_name||r(u.permission_name))}),m=c=>{var Z,ee;const l=o.url.toLowerCase();if(c.alias&&l.startsWith("/"+c.alias.toLowerCase())||c.link&&c.link!=="#"&&l.startsWith("/"+c.link.toLowerCase()))return!0;const u=((ee=(Z=o.props.menus)==null?void 0:Z.sidebar_nav)==null?void 0:ee[c.id])||[];for(const $ of u)if($.alias&&l.startsWith("/"+$.alias.toLowerCase())||$.link&&$.link!=="#"&&l.startsWith("/"+$.link.toLowerCase()))return!0;return!1};console.log(o.props.auth.user);const S=c=>{oe.put(route("currentteam.update"),{team_id:c.id},{preserveState:!1})},v=()=>{oe.post(route("logout"))},w=async c=>{try{await Ce.post("/context/selectentity",{entity_id:c}),window.location.href="/dashboard"}catch(l){console.error("Entity switch failed",l)}};return(c,l)=>(s(),i("div",null,[g(_(xe),{title:e.title},null,8,["title"]),g(Ge),g(_(pe)),g(_(fe)),n("div",ro,[n("nav",so,[n("div",ao,[n("div",io,[n("div",lo,[g(_(te),{href:c.route("dashboard")},{default:p(()=>{var u;return[(u=d.value)!=null&&u.entity_logo?(s(),i("img",{key:0,src:`/storage/${d.value.entity_logo}`,alt:"Logo",class:"block h-9 w-auto object-contain brightness-125"},null,8,co)):(s(),b(je,{key:1,class:"block h-9 w-auto brightness-125"}))]}),_:1},8,["href"])]),n("div",uo,[n("div",mo,[(s(!0),i(C,null,D(a.value,u=>(s(),b(_(te),{key:u.id,href:u.link==="#"?"#":u.link.startsWith("/")?u.link:"/"+u.link,class:k([m(u)?"border-b-4 border-amber-400 text-amber-400 bg-white/10":"border-b-4 border-transparent text-blue-200 hover:text-amber-300 hover:bg-white/10 hover:border-amber-300/50","flex flex-col items-center justify-center py-2 px-3 md:px-5 min-w-[3.5rem] md:min-w-[5rem] group focus:outline-none transition-all duration-200 cursor-pointer"])},{default:p(()=>[(s(),b(I(t[u.icon]||t.HomeIcon),{class:k([m(u)?"text-amber-400":"text-blue-300 group-hover:text-amber-300","h-6 w-6 md:h-7 md:w-7 mb-0.5 transition-colors duration-200 shrink-0"]),"aria-hidden":"true"},null,8,["class"])),n("span",{class:k([m(u)?"text-amber-400 font-bold":"text-blue-200 group-hover:text-amber-300 font-semibold","text-[9px] md:text-[10px] uppercase tracking-wide whitespace-nowrap transition-colors duration-200"])},y(u.title),3)]),_:2},1032,["href","class"]))),128))])]),n("div",po,[c.$page.props.user_entities&&c.$page.props.user_entities.length>0?(s(),i("div",fo,[g(N,{align:"right",width:"64"},{trigger:p(()=>{var u;return[n("button",go,[l[0]||(l[0]=n("span",{class:"w-2 h-2 rounded-full bg-amber-400 flex-shrink-0"},null,-1)),n("span",ho,y(((u=c.$page.props.active_entity)==null?void 0:u.entity_name)??"Workspace"),1),l[1]||(l[1]=n("svg",{class:"w-3.5 h-3.5 text-blue-300",fill:"none",viewBox:"0 0 24 24","stroke-width":"1.5",stroke:"currentColor"},[n("path",{"stroke-linecap":"round","stroke-linejoin":"round",d:"M8.25 15L12 18.75 15.75 15m-7.5-6L12 5.25 15.75 9"})],-1))])]}),content:p(()=>[n("div",bo,[l[3]||(l[3]=n("div",{class:"px-4 py-2 text-xs text-gray-400 font-semibold uppercase tracking-wide border-b border-gray-100 dark:border-gray-700"}," Switch Workspace ",-1)),n("div",vo,[(s(!0),i(C,null,D(c.$page.props.user_entities,u=>(s(),i("button",{key:u.entity_id,onClick:Z=>w(u.entity_id),class:k(["w-full text-left flex items-center gap-3 px-4 py-3 text-sm hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors",u.is_active?"text-pastelbeaver-600 dark:text-pastelbeaver-400 bg-pastelbeaver-50 dark:bg-pastelbeaver-900/20":"text-gray-700 dark:text-gray-300"])},[n("div",wo,[u.entity_logo?(s(),i("img",{key:0,src:`/storage/${u.entity_logo}`,class:"w-full h-full object-contain p-0.5"},null,8,ko)):(s(),i("span",xo,y(u.entity_name.charAt(0)),1))]),n("div",Co,[n("p",jo,y(u.entity_name),1),n("p",Io,y(u.role_name),1)]),u.is_active?(s(),i("svg",So,[...l[2]||(l[2]=[n("path",{"stroke-linecap":"round","stroke-linejoin":"round",d:"M4.5 12.75l6 6 9-13.5"},null,-1)])])):h("",!0)],10,yo))),128))])])]),_:1})])):h("",!0),n("button",_o,[g(_(se),{class:"h-6 w-6 md:h-7 md:w-7","aria-hidden":"true"}),l[4]||(l[4]=n("span",{class:"absolute top-1.5 right-1.5 inline-flex items-center justify-center px-1.5 py-0.5 text-[10px] font-bold leading-none text-white transform translate-x-1/4 -translate-y-1/4 bg-red-500 rounded-full ring-2 ring-[#1e3a5f]"}," 0 ",-1))]),n("div",Mo,[c.$page.props.jetstream.hasTeamFeatures?(s(),b(N,{key:0,align:"right",width:"60"},{trigger:p(()=>[n("span",Ao,[n("button",$o,[c.$page.props.jetstream.managesProfilePhotos?(s(),i("img",{key:0,class:"size-6 rounded-full object-cover me-2 focus:border-pastelbeaver-400",src:"/storage/"+c.$page.props.auth.user.profile_photo_path,alt:c.$page.props.auth.user.username},null,8,Po)):(s(),i("svg",To,[...l[5]||(l[5]=[n("path",{"stroke-linecap":"round","stroke-linejoin":"round",d:"M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z"},null,-1)])])),j(" "+y(c.$page.props.auth.user.current_team.name)+" ",1),l[6]||(l[6]=n("svg",{class:"ms-2 -me-0.5 size-4",xmlns:"http://www.w3.org/2000/svg",fill:"none",viewBox:"0 0 24 24","stroke-width":"1.5",stroke:"currentColor"},[n("path",{"stroke-linecap":"round","stroke-linejoin":"round",d:"M8.25 15L12 18.75 15.75 15m-7.5-6L12 5.25 15.75 9"})],-1))])])]),content:p(()=>[n("div",Bo,[l[12]||(l[12]=n("div",{class:"block px-4 py-2 text-xs text-gray-400"}," Manage Team ",-1)),g(M,{href:c.route("teams.show",c.$page.props.auth.user.current_team)},{default:p(()=>[...l[7]||(l[7]=[j(" Team Settings ",-1)])]),_:1},8,["href"]),c.$page.props.jetstream.canCreateTeams?(s(),b(M,{key:0,href:c.route("teams.create")},{default:p(()=>[...l[8]||(l[8]=[j(" Create New Team ",-1)])]),_:1},8,["href"])):h("",!0),c.$page.props.auth.user.all_teams.length>1?(s(),i(C,{key:1},[l[10]||(l[10]=n("div",{class:"border-t border-gray-200 dark:border-gray-600"},null,-1)),l[11]||(l[11]=n("div",{class:"block px-4 py-2 text-xs text-gray-400"}," Switch Teams ",-1)),(s(!0),i(C,null,D(c.$page.props.auth.user.all_teams,u=>(s(),i("form",{key:u.id,onSubmit:G(Z=>S(u),["prevent"])},[g(M,{as:"button"},{default:p(()=>[n("div",Eo,[u.id==c.$page.props.auth.user.current_team_id?(s(),i("svg",Oo,[...l[9]||(l[9]=[n("path",{"stroke-linecap":"round","stroke-linejoin":"round",d:"M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"},null,-1)])])):h("",!0),n("div",null,y(u.name),1)])]),_:2},1024)],40,Lo))),128))],64)):h("",!0)])]),_:1})):h("",!0)]),n("div",Zo,[g(N,{align:"right",width:"48"},{trigger:p(()=>[c.$page.props.jetstream.managesProfilePhotos?(s(),i("button",zo,[n("img",{class:"size-8 rounded-full object-cover",src:"/storage/"+c.$page.props.auth.user.profile_photo_path,alt:c.$page.props.auth.user.username},null,8,Vo)])):(s(),i("span",Do,[n("button",Ro,[j(y(c.$page.props.auth.user.username)+" ",1),l[13]||(l[13]=n("svg",{class:"ms-2 -me-0.5 size-4",xmlns:"http://www.w3.org/2000/svg",fill:"none",viewBox:"0 0 24 24","stroke-width":"1.5",stroke:"currentColor"},[n("path",{"stroke-linecap":"round","stroke-linejoin":"round",d:"M19.5 8.25l-7.5 7.5-7.5-7.5"})],-1))])]))]),content:p(()=>[l[17]||(l[17]=n("div",{class:"block px-4 py-2 text-xs font-semibold tracking-wider text-gray-400 uppercase"}," Manage Account ",-1)),g(M,{href:c.route("profile.show")},{default:p(()=>[...l[14]||(l[14]=[n("div",{class:"flex items-center group hover:text-pastelbeaver-600 transition-colors"},[n("svg",{xmlns:"http://www.w3.org/2000/svg",fill:"none",viewBox:"0 0 24 24","stroke-width":"1.5",stroke:"currentColor",class:"w-4 h-4 mr-2 text-gray-400 group-hover:text-pastelbeaver-500"},[n("path",{"stroke-linecap":"round","stroke-linejoin":"round",d:"M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z"})]),j(" Profile ")],-1)])]),_:1},8,["href"]),c.$page.props.jetstream.hasApiFeatures?(s(),b(M,{key:0,href:c.route("apitokens.index")},{default:p(()=>[...l[15]||(l[15]=[n("div",{class:"flex items-center group hover:text-pastelbeaver-600 transition-colors"},[n("svg",{xmlns:"http://www.w3.org/2000/svg",fill:"none",viewBox:"0 0 24 24","stroke-width":"1.5",stroke:"currentColor",class:"w-4 h-4 mr-2 text-gray-400 group-hover:text-pastelbeaver-500"},[n("path",{"stroke-linecap":"round","stroke-linejoin":"round",d:"M14.25 9.75 16.5 12l-2.25 2.25m-4.5 0L7.5 12l2.25-2.25M6 20.25h12A2.25 2.25 0 0 0 20.25 18V6A2.25 2.25 0 0 0 18 3.75H6A2.25 2.25 0 0 0 3.75 6v12A2.25 2.25 0 0 0 6 20.25Z"})]),j(" API Tokens ")],-1)])]),_:1},8,["href"])):h("",!0),l[18]||(l[18]=n("div",{class:"border-t border-gray-200 dark:border-gray-600 my-1"},null,-1)),n("form",{onSubmit:G(v,["prevent"])},[g(M,{as:"button"},{default:p(()=>[...l[16]||(l[16]=[n("div",{class:"flex items-center text-red-600 hover:text-red-700 transition-colors"},[n("svg",{xmlns:"http://www.w3.org/2000/svg",fill:"none",viewBox:"0 0 24 24","stroke-width":"1.5",stroke:"currentColor",class:"w-4 h-4 mr-2"},[n("path",{"stroke-linecap":"round","stroke-linejoin":"round",d:"M8.25 9V5.25A2.25 2.25 0 0 1 10.5 3h6a2.25 2.25 0 0 1 2.25 2.25v13.5A2.25 2.25 0 0 1 16.5 21h-6a2.25 2.25 0 0 1-2.25-2.25V15m-3 0-3-3m0 0 3-3m-3 3H15"})]),j(" Log Out ")],-1)])]),_:1})],32)]),_:1})])])])])]),c.$slots.header?(s(),i("header",Ho,[n("div",Fo,[A(c.$slots,"header")])])):h("",!0),n("main",Uo,[A(c.$slots,"default")])])]))}};export{cn as _,ot as a,tt as b,Xe as c,We as r,pe as s,ze as u};
