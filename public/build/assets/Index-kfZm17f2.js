import{M as te,W as S,X as j,Z as ne,$ as H,a0 as $,L as v,a1 as ae,o as b,d as g,a as d,I as E,r as R,c as _,U as L,f as k,F as V,k as U,t as y,a2 as re,p as oe,w as f,b as p,i as m,e as N,E as W,g as z,l as B}from"./app-CteCJMeo.js";import M from"./sweetalert2.esm.all-B_S-52a2.js";import{_ as ie}from"./AppLayout-Ctt5__pi.js";import{M as se}from"./ModuleSubTopNav-oURUTc84.js";import{_ as le}from"./BatchCreateForm.vue_vue_type_script_setup_true_lang-B5ZIDfUt.js";import{_ as de}from"./BatchEditForm.vue_vue_type_script_setup_true_lang-CwSAlIje.js";import ce from"./DispatchSection-DSNfb4Ho.js";import{B as ue}from"./BaseDataTable-DLC5xING.js";import{_ as q}from"./BaseSelect.vue_vue_type_style_index_0_lang-BlG6edte.js";import{s as x}from"./index-zs5S_WM9.js";import{s as pe}from"./index-IeeHo4Lj.js";import{s as be}from"./index-BV-RC-vE.js";import{s as fe}from"./index-BQvHkjjp.js";import{R as ve,a as he}from"./index-BA_P5gVV.js";import{s as Q}from"./index-CU0B8b0d.js";import{_ as F}from"./BaseButton.vue_vue_type_script_setup_true_lang-I6W-fYNv.js";import"./ApplicationMark-BdgEQhZz.js";import"./DropdownLink-D03hP5R1.js";import"./index-B4NkT1MU.js";import"./index-DS5spWZz.js";import"./index-DCT0j5fq.js";import"./index-C_IOYlEz.js";import"./index-BV229_hF.js";import"./index-BC-DrhNz.js";import"./ShoppingCartIcon-Cd3IejUQ.js";import"./TruckIcon-C0JGSuKw.js";import"./ScaleIcon-EuZNuJI0.js";import"./UserPlusIcon-BMg_CRAi.js";import"./DocumentTextIcon-e41mpnug.js";import"./ClockIcon-D5M3jGFA.js";import"./ArrowUpRightIcon-D7tIj6_j.js";import"./BriefcaseIcon-BM-7yfEo.js";import"./IdentificationIcon-D47OVEX7.js";import"./Cog6ToothIcon-DoTMig8H.js";import"./BaseInputNumber.vue_vue_type_style_index_0_lang-Bi3tp-Rp.js";import"./index-BoxkqDyv.js";import"./index-Bt5JRo7n.js";import"./BaseField.vue_vue_type_script_setup_true_lang-D9pc9Hk0.js";import"./BaseDatePicker.vue_vue_type_style_index_0_lang-VCkVd4rm.js";import"./index-OoqPO7u3.js";import"./index-CZhtx1rw.js";import"./index-BaQuaLfa.js";import"./useWeighbridge-It9mWF_B.js";import"./PlusCircleIcon-BoFt-S2J.js";import"./InformationCircleIcon-B9rsoIOw.js";import"./ArrowDownTrayIcon-CB4JLQfy.js";import"./BaseInput-BZXcuoO7.js";import"./BaseFormActions-BdeUEiDa.js";import"./ListBulletIcon-DDJJVPE6.js";import"./DispatchWeightsForm.vue_vue_type_script_setup_true_lang-C0ErzRqP.js";import"./BanknotesIcon-CFAw6s7y.js";import"./index-BgCk6ydw.js";import"./BaseDeleteButton-BescayX2.js";import"./TrashIcon-DQbVsoAv.js";import"./TagIcon-b0yOZfeA.js";import"./CheckCircleIcon-BD58FGJp.js";import"./ArrowPathIcon-DtLmpICu.js";import"./PlusIcon-CzLTRxDn.js";import"./ArchiveBoxIcon-B_i-BakF.js";import"./BuildingOfficeIcon-Cm9TL2iN.js";import"./BeakerIcon-BKlfQ0Xd.js";import"./MagnifyingGlassIcon-BHg3LsbM.js";import"./index-BqJThybE.js";import"./index-BGmVdkZB.js";var me=`
    .p-tabview-tablist-container {
        position: relative;
    }

    .p-tabview-scrollable > .p-tabview-tablist-container {
        overflow: hidden;
    }

    .p-tabview-tablist-scroll-container {
        overflow-x: auto;
        overflow-y: hidden;
        scroll-behavior: smooth;
        scrollbar-width: none;
        overscroll-behavior: contain auto;
    }

    .p-tabview-tablist-scroll-container::-webkit-scrollbar {
        display: none;
    }

    .p-tabview-tablist {
        display: flex;
        margin: 0;
        padding: 0;
        list-style-type: none;
        flex: 1 1 auto;
        background: dt('tabview.tab.list.background');
        border: 1px solid dt('tabview.tab.list.border.color');
        border-width: 0 0 1px 0;
        position: relative;
    }

    .p-tabview-tab-header {
        cursor: pointer;
        user-select: none;
        display: flex;
        align-items: center;
        text-decoration: none;
        position: relative;
        overflow: hidden;
        border-style: solid;
        border-width: 0 0 1px 0;
        border-color: transparent transparent dt('tabview.tab.border.color') transparent;
        color: dt('tabview.tab.color');
        padding: 1rem 1.125rem;
        font-weight: 600;
        border-top-right-radius: dt('border.radius.md');
        border-top-left-radius: dt('border.radius.md');
        transition:
            color dt('tabview.transition.duration'),
            outline-color dt('tabview.transition.duration');
        margin: 0 0 -1px 0;
        outline-color: transparent;
    }

    .p-tabview-tablist-item:not(.p-disabled) .p-tabview-tab-header:focus-visible {
        outline: dt('focus.ring.width') dt('focus.ring.style') dt('focus.ring.color');
        outline-offset: -1px;
    }

    .p-tabview-tablist-item:not(.p-highlight):not(.p-disabled):hover > .p-tabview-tab-header {
        color: dt('tabview.tab.hover.color');
    }

    .p-tabview-tablist-item.p-highlight > .p-tabview-tab-header {
        color: dt('tabview.tab.active.color');
    }

    .p-tabview-tab-title {
        line-height: 1;
        white-space: nowrap;
    }

    .p-tabview-next-button,
    .p-tabview-prev-button {
        position: absolute;
        top: 0;
        margin: 0;
        padding: 0;
        z-index: 2;
        height: 100%;
        display: flex;
        align-items: center;
        justify-content: center;
        background: dt('tabview.nav.button.background');
        color: dt('tabview.nav.button.color');
        width: 2.5rem;
        border-radius: 0;
        outline-color: transparent;
        transition:
            color dt('tabview.transition.duration'),
            outline-color dt('tabview.transition.duration');
        box-shadow: dt('tabview.nav.button.shadow');
        border: none;
        cursor: pointer;
        user-select: none;
    }

    .p-tabview-next-button:focus-visible,
    .p-tabview-prev-button:focus-visible {
        outline: dt('focus.ring.width') dt('focus.ring.style') dt('focus.ring.color');
        outline-offset: dt('focus.ring.offset');
    }

    .p-tabview-next-button:hover,
    .p-tabview-prev-button:hover {
        color: dt('tabview.nav.button.hover.color');
    }

    .p-tabview-prev-button {
        left: 0;
    }

    .p-tabview-next-button {
        right: 0;
    }

    .p-tabview-panels {
        background: dt('tabview.tab.panel.background');
        color: dt('tabview.tab.panel.color');
        padding: 0.875rem 1.125rem 1.125rem 1.125rem;
    }

    .p-tabview-ink-bar {
        z-index: 1;
        display: block;
        position: absolute;
        bottom: -1px;
        height: 1px;
        background: dt('tabview.tab.active.border.color');
        transition: 250ms cubic-bezier(0.35, 0, 0.25, 1);
    }
`,ge={root:function(e){var n=e.props;return["p-tabview p-component",{"p-tabview-scrollable":n.scrollable}]},navContainer:"p-tabview-tablist-container",prevButton:"p-tabview-prev-button",navContent:"p-tabview-tablist-scroll-container",nav:"p-tabview-tablist",tab:{header:function(e){var n=e.instance,a=e.tab,l=e.index;return["p-tabview-tablist-item",n.getTabProp(a,"headerClass"),{"p-tabview-tablist-item-active":n.d_activeIndex===l,"p-disabled":n.getTabProp(a,"disabled")}]},headerAction:"p-tabview-tab-header",headerTitle:"p-tabview-tab-title",content:function(e){var n=e.instance,a=e.tab;return["p-tabview-panel",n.getTabProp(a,"contentClass")]}},inkbar:"p-tabview-ink-bar",nextButton:"p-tabview-next-button",panelContainer:"p-tabview-panels"},we=te.extend({name:"tabview",style:me,classes:ge}),ye={name:"BaseTabView",extends:he,props:{activeIndex:{type:Number,default:0},lazy:{type:Boolean,default:!1},scrollable:{type:Boolean,default:!1},tabindex:{type:Number,default:0},selectOnFocus:{type:Boolean,default:!1},prevButtonProps:{type:null,default:null},nextButtonProps:{type:null,default:null},prevIcon:{type:String,default:void 0},nextIcon:{type:String,default:void 0}},style:we,provide:function(){return{$pcTabs:void 0,$pcTabView:this,$parentInstance:this}}},Y={name:"TabView",extends:ye,inheritAttrs:!1,emits:["update:activeIndex","tab-change","tab-click"],data:function(){return{d_activeIndex:this.activeIndex,isPrevButtonDisabled:!0,isNextButtonDisabled:!1}},watch:{activeIndex:function(e){this.d_activeIndex=e,this.scrollInView({index:e})}},mounted:function(){console.warn("Deprecated since v4. Use Tabs component instead."),this.updateInkBar(),this.scrollable&&this.updateButtonState()},updated:function(){this.updateInkBar(),this.scrollable&&this.updateButtonState()},methods:{isTabPanel:function(e){return e.type.name==="TabPanel"},isTabActive:function(e){return this.d_activeIndex===e},getTabProp:function(e,n){return e.props?e.props[n]:void 0},getKey:function(e,n){return this.getTabProp(e,"header")||n},getTabHeaderActionId:function(e){return"".concat(this.$id,"_").concat(e,"_header_action")},getTabContentId:function(e){return"".concat(this.$id,"_").concat(e,"_content")},getTabPT:function(e,n,a){var l=this.tabs.length,r={props:e.props,parent:{instance:this,props:this.$props,state:this.$data},context:{index:a,count:l,first:a===0,last:a===l-1,active:this.isTabActive(a)}};return v(this.ptm("tabpanel.".concat(n),{tabpanel:r}),this.ptm("tabpanel.".concat(n),r),this.ptmo(this.getTabProp(e,"pt"),n,r))},onScroll:function(e){this.scrollable&&this.updateButtonState(),e.preventDefault()},onPrevButtonClick:function(){var e=this.$refs.content,n=S(e),a=e.scrollLeft-n;e.scrollLeft=a<=0?0:a},onNextButtonClick:function(){var e=this.$refs.content,n=S(e)-this.getVisibleButtonWidths(),a=e.scrollLeft+n,l=e.scrollWidth-n;e.scrollLeft=a>=l?l:a},onTabClick:function(e,n,a){this.changeActiveIndex(e,n,a),this.$emit("tab-click",{originalEvent:e,index:a})},onTabKeyDown:function(e,n,a){switch(e.code){case"ArrowLeft":this.onTabArrowLeftKey(e);break;case"ArrowRight":this.onTabArrowRightKey(e);break;case"Home":this.onTabHomeKey(e);break;case"End":this.onTabEndKey(e);break;case"PageDown":this.onPageDownKey(e);break;case"PageUp":this.onPageUpKey(e);break;case"Enter":case"NumpadEnter":case"Space":this.onTabEnterKey(e,n,a);break}},onTabArrowRightKey:function(e){var n=this.findNextHeaderAction(e.target.parentElement);n?this.changeFocusedTab(e,n):this.onTabHomeKey(e),e.preventDefault()},onTabArrowLeftKey:function(e){var n=this.findPrevHeaderAction(e.target.parentElement);n?this.changeFocusedTab(e,n):this.onTabEndKey(e),e.preventDefault()},onTabHomeKey:function(e){var n=this.findFirstHeaderAction();this.changeFocusedTab(e,n),e.preventDefault()},onTabEndKey:function(e){var n=this.findLastHeaderAction();this.changeFocusedTab(e,n),e.preventDefault()},onPageDownKey:function(e){this.scrollInView({index:this.$refs.nav.children.length-2}),e.preventDefault()},onPageUpKey:function(e){this.scrollInView({index:0}),e.preventDefault()},onTabEnterKey:function(e,n,a){this.changeActiveIndex(e,n,a),e.preventDefault()},findNextHeaderAction:function(e){var n=arguments.length>1&&arguments[1]!==void 0?arguments[1]:!1,a=n?e:e.nextElementSibling;return a?H(a,"data-p-disabled")||H(a,"data-pc-section")==="inkbar"?this.findNextHeaderAction(a):$(a,'[data-pc-section="headeraction"]'):null},findPrevHeaderAction:function(e){var n=arguments.length>1&&arguments[1]!==void 0?arguments[1]:!1,a=n?e:e.previousElementSibling;return a?H(a,"data-p-disabled")||H(a,"data-pc-section")==="inkbar"?this.findPrevHeaderAction(a):$(a,'[data-pc-section="headeraction"]'):null},findFirstHeaderAction:function(){return this.findNextHeaderAction(this.$refs.nav.firstElementChild,!0)},findLastHeaderAction:function(){return this.findPrevHeaderAction(this.$refs.nav.lastElementChild,!0)},changeActiveIndex:function(e,n,a){!this.getTabProp(n,"disabled")&&this.d_activeIndex!==a&&(this.d_activeIndex=a,this.$emit("update:activeIndex",a),this.$emit("tab-change",{originalEvent:e,index:a}),this.scrollInView({index:a}))},changeFocusedTab:function(e,n){if(n&&(ne(n),this.scrollInView({element:n}),this.selectOnFocus)){var a=parseInt(n.parentElement.dataset.pcIndex,10),l=this.tabs[a];this.changeActiveIndex(e,l,a)}},scrollInView:function(e){var n=e.element,a=e.index,l=a===void 0?-1:a,r=n||this.$refs.nav.children[l];r&&r.scrollIntoView&&r.scrollIntoView({block:"nearest"})},updateInkBar:function(){var e=this.$refs.nav.children[this.d_activeIndex];this.$refs.inkbar.style.width=S(e)+"px",this.$refs.inkbar.style.left=j(e).left-j(this.$refs.nav).left+"px"},updateButtonState:function(){var e=this.$refs.content,n=e.scrollLeft,a=e.scrollWidth,l=S(e);this.isPrevButtonDisabled=n===0,this.isNextButtonDisabled=parseInt(n)===a-l},getVisibleButtonWidths:function(){var e=this.$refs,n=e.prevBtn,a=e.nextBtn;return[n,a].reduce(function(l,r){return r?l+S(r):l},0)}},computed:{tabs:function(){var e=this;return this.$slots.default().reduce(function(n,a){return e.isTabPanel(a)?n.push(a):a.children&&a.children instanceof Array&&a.children.forEach(function(l){e.isTabPanel(l)&&n.push(l)}),n},[])},prevButtonAriaLabel:function(){return this.$primevue.config.locale.aria?this.$primevue.config.locale.aria.previous:void 0},nextButtonAriaLabel:function(){return this.$primevue.config.locale.aria?this.$primevue.config.locale.aria.next:void 0}},directives:{ripple:ve},components:{ChevronLeftIcon:be,ChevronRightIcon:fe}};function D(t){"@babel/helpers - typeof";return D=typeof Symbol=="function"&&typeof Symbol.iterator=="symbol"?function(e){return typeof e}:function(e){return e&&typeof Symbol=="function"&&e.constructor===Symbol&&e!==Symbol.prototype?"symbol":typeof e},D(t)}function X(t,e){var n=Object.keys(t);if(Object.getOwnPropertySymbols){var a=Object.getOwnPropertySymbols(t);e&&(a=a.filter(function(l){return Object.getOwnPropertyDescriptor(t,l).enumerable})),n.push.apply(n,a)}return n}function w(t){for(var e=1;e<arguments.length;e++){var n=arguments[e]!=null?arguments[e]:{};e%2?X(Object(n),!0).forEach(function(a){xe(t,a,n[a])}):Object.getOwnPropertyDescriptors?Object.defineProperties(t,Object.getOwnPropertyDescriptors(n)):X(Object(n)).forEach(function(a){Object.defineProperty(t,a,Object.getOwnPropertyDescriptor(n,a))})}return t}function xe(t,e,n){return(e=ke(e))in t?Object.defineProperty(t,e,{value:n,enumerable:!0,configurable:!0,writable:!0}):t[e]=n,t}function ke(t){var e=Te(t,"string");return D(e)=="symbol"?e:e+""}function Te(t,e){if(D(t)!="object"||!t)return t;var n=t[Symbol.toPrimitive];if(n!==void 0){var a=n.call(t,e);if(D(a)!="object")return a;throw new TypeError("@@toPrimitive must return a primitive value.")}return(e==="string"?String:Number)(t)}var Be=["tabindex","aria-label"],_e=["data-p-active","data-p-disabled","data-pc-index"],Ie=["id","tabindex","aria-disabled","aria-selected","aria-controls","onClick","onKeydown"],Pe=["tabindex","aria-label"],Ae=["id","aria-labelledby","data-pc-index","data-p-active"];function Ce(t,e,n,a,l,r){var I=ae("ripple");return b(),g("div",v({class:t.cx("root"),role:"tablist"},t.ptmi("root")),[d("div",v({class:t.cx("navContainer")},t.ptm("navContainer")),[t.scrollable&&!l.isPrevButtonDisabled?E((b(),g("button",v({key:0,ref:"prevBtn",type:"button",class:t.cx("prevButton"),tabindex:t.tabindex,"aria-label":r.prevButtonAriaLabel,onClick:e[0]||(e[0]=function(){return r.onPrevButtonClick&&r.onPrevButtonClick.apply(r,arguments)})},w(w({},t.prevButtonProps),t.ptm("prevButton")),{"data-pc-group-section":"navbutton"}),[R(t.$slots,"previcon",{},function(){return[(b(),_(L(t.prevIcon?"span":"ChevronLeftIcon"),v({"aria-hidden":"true",class:t.prevIcon},t.ptm("prevIcon")),null,16,["class"]))]})],16,Be)),[[I]]):k("",!0),d("div",v({ref:"content",class:t.cx("navContent"),onScroll:e[1]||(e[1]=function(){return r.onScroll&&r.onScroll.apply(r,arguments)})},t.ptm("navContent")),[d("ul",v({ref:"nav",class:t.cx("nav")},t.ptm("nav")),[(b(!0),g(V,null,U(r.tabs,function(i,c){return b(),g("li",v({key:r.getKey(i,c),style:r.getTabProp(i,"headerStyle"),class:t.cx("tab.header",{tab:i,index:c}),role:"presentation"},{ref_for:!0},w(w(w({},r.getTabProp(i,"headerProps")),r.getTabPT(i,"root",c)),r.getTabPT(i,"header",c)),{"data-pc-name":"tabpanel","data-p-active":l.d_activeIndex===c,"data-p-disabled":r.getTabProp(i,"disabled"),"data-pc-index":c}),[E((b(),g("a",v({id:r.getTabHeaderActionId(c),class:t.cx("tab.headerAction"),tabindex:r.getTabProp(i,"disabled")||!r.isTabActive(c)?-1:t.tabindex,role:"tab","aria-disabled":r.getTabProp(i,"disabled"),"aria-selected":r.isTabActive(c),"aria-controls":r.getTabContentId(c),onClick:function(P){return r.onTabClick(P,i,c)},onKeydown:function(P){return r.onTabKeyDown(P,i,c)}},{ref_for:!0},w(w({},r.getTabProp(i,"headerActionProps")),r.getTabPT(i,"headerAction",c))),[i.props&&i.props.header?(b(),g("span",v({key:0,class:t.cx("tab.headerTitle")},{ref_for:!0},r.getTabPT(i,"headerTitle",c)),y(i.props.header),17)):k("",!0),i.children&&i.children.header?(b(),_(L(i.children.header),{key:1})):k("",!0)],16,Ie)),[[I]])],16,_e)}),128)),d("li",v({ref:"inkbar",class:t.cx("inkbar"),role:"presentation","aria-hidden":"true"},t.ptm("inkbar")),null,16)],16)],16),t.scrollable&&!l.isNextButtonDisabled?E((b(),g("button",v({key:1,ref:"nextBtn",type:"button",class:t.cx("nextButton"),tabindex:t.tabindex,"aria-label":r.nextButtonAriaLabel,onClick:e[2]||(e[2]=function(){return r.onNextButtonClick&&r.onNextButtonClick.apply(r,arguments)})},w(w({},t.nextButtonProps),t.ptm("nextButton")),{"data-pc-group-section":"navbutton"}),[R(t.$slots,"nexticon",{},function(){return[(b(),_(L(t.nextIcon?"span":"ChevronRightIcon"),v({"aria-hidden":"true",class:t.nextIcon},t.ptm("nextIcon")),null,16,["class"]))]})],16,Pe)),[[I]]):k("",!0)],16),d("div",v({class:t.cx("panelContainer")},t.ptm("panelContainer")),[(b(!0),g(V,null,U(r.tabs,function(i,c){return b(),g(V,{key:r.getKey(i,c)},[!t.lazy||r.isTabActive(c)?E((b(),g("div",v({key:0,id:r.getTabContentId(c),style:r.getTabProp(i,"contentStyle"),class:t.cx("tab.content",{tab:i}),role:"tabpanel","aria-labelledby":r.getTabHeaderActionId(c)},{ref_for:!0},w(w(w({},r.getTabProp(i,"contentProps")),r.getTabPT(i,"root",c)),r.getTabPT(i,"content",c)),{"data-pc-name":"tabpanel","data-pc-index":c,"data-p-active":l.d_activeIndex===c}),[(b(),_(L(i)))],16,Ae)),[[re,t.lazy?!0:r.isTabActive(c)]]):k("",!0)],64)}),128))],16)],16)}Y.render=Ce;const Se={class:"py-2 px-4"},De={class:"max-w-7xl mx-auto mt-4 space-y-4"},Ke={key:0,class:"rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs font-medium text-amber-700"},Oe={class:"bg-white shadow-xl sm:rounded-lg"},He={class:"flex flex-col gap-1.5"},Ee={class:"flex flex-col gap-1.5"},Le={key:0,class:"flex flex-col"},Ne={class:"text-xs font-bold text-slate-700"},Ve={class:"text-[10px] text-slate-400 font-medium uppercase"},Fe={key:1,class:"text-xs text-slate-300 italic"},je=["onClick"],$e={class:"text-[10px] font-black text-indigo-400 uppercase tracking-widest"},Re={class:"flex flex-col"},Ue={class:"text-xs font-bold text-slate-700"},We={class:"text-[10px] text-slate-400 font-medium uppercase"},ze={class:"flex flex-col"},Me={class:"text-xs font-black text-slate-800"},qe={class:"text-[10px] text-emerald-600 font-black tracking-tighter uppercase"},Qe={class:"text-xs font-semibold text-slate-700"},Xe={class:"text-xs font-bold text-slate-700"},Ye={class:"flex justify-end gap-2"},Ze={class:"p-1 bg-slate-50/50 border-y border-slate-100"},Ge={class:""},Je={class:""},un=oe({__name:"Index",props:{batches:{},workOrders:{},trucks:{},customers:{},transporters:{},personnel:{},taxes:{},products:{},loading_sites:{},unloading_sites:{},uoms:{},statuses:{},schemaWarning:{},nextBatchNo:{},batchingSettings:{}},setup(t){const e=t,n=z(()=>({trucks:e.trucks,transporters:e.transporters,personnel:e.personnel,taxes:e.taxes,uoms:e.uoms,unloading_sites:e.unloading_sites,loading_sites:e.loading_sites})),a=B({global:{value:null,matchMode:"contains"},status:{value:null,matchMode:"equals"},"work_order.id":{value:null,matchMode:"equals"},"work_order.customer.id":{value:null,matchMode:"equals"}}),l=B(null),r=B(null),I=z(()=>{let u=e.batches;if(l.value){const s=new Date(l.value);u=u.filter(o=>new Date(o.created_at)>=s)}if(r.value){const s=new Date(r.value);s.setHours(23,59,59,999),u=u.filter(o=>new Date(o.created_at)<=s)}return u}),i=B({}),c=B(0),K=B(30),P=u=>{i.value[u.id]?i.value={}:i.value={[u.id]:!0}},O=()=>{i.value={}},Z=u=>u===4?"success":u===2||u===3?"info":u===5?"danger":"warn",G=u=>{var s;return((s=e.statuses.find(o=>o.value===u))==null?void 0:s.label)??"Unknown"},J=u=>{M.fire({title:"Delete batch?",text:`Batch #${u.batch_no} will be deleted.`,icon:"warning",showCancelButton:!0,confirmButtonColor:"#dc2626",confirmButtonText:"Yes, delete"}).then(s=>{s.isConfirmed&&W.delete(route("batches.destroy",u.id),{preserveScroll:!0,onSuccess:()=>{M.fire({toast:!0,position:"top-end",icon:"success",title:"Batch deleted successfully.",showConfirmButton:!1,timer:2500})}})})},ee=u=>{window.open(route("batches.download",u),"_blank")};return(u,s)=>(b(),_(ie,{title:"Batches"},{default:f(()=>[d("div",Se,[p(se),d("div",De,[t.schemaWarning?(b(),g("div",Ke,y(t.schemaWarning),1)):k("",!0),p(le,{workOrders:t.workOrders,trucks:t.trucks,transporters:t.transporters,personnel:t.personnel,products:t.products,uoms:t.uoms,unloading_sites:t.unloading_sites,loading_sites:t.loading_sites,taxes:t.taxes,statuses:t.statuses,nextBatchNo:t.nextBatchNo},null,8,["workOrders","trucks","transporters","personnel","products","uoms","unloading_sites","loading_sites","taxes","statuses","nextBatchNo"]),s[10]||(s[10]=d("hr",{class:"border-slate-200 border-dashed"},null,-1)),d("div",Oe,[p(ue,{value:I.value,first:c.value,"onUpdate:first":s[2]||(s[2]=o=>c.value=o),rows:K.value,"onUpdate:rows":s[3]||(s[3]=o=>K.value=o),filters:a.value,"onUpdate:filters":s[4]||(s[4]=o=>a.value=o),expandedRows:i.value,"onUpdate:expandedRows":s[5]||(s[5]=o=>i.value=o),dateFrom:l.value,"onUpdate:dateFrom":s[6]||(s[6]=o=>l.value=o),dateTo:r.value,"onUpdate:dateTo":s[7]||(s[7]=o=>r.value=o),dataKey:"id",paginator:"",stripedRows:"",removableSort:"",rowHover:"",filterDisplay:"menu",class:"cursor-pointer",globalFilterFields:["batch_no","work_order.order_no","truck.registration","work_order.customer.legal_name","work_order.mix_design.design_name"],showSerial:"",heading:"List Of Batches",headingIcon:"ListBulletIcon",showSearch:!0,showExport:"",exportFilename:"batch-report"},{filters:f(()=>[d("div",He,[s[8]||(s[8]=d("label",{class:"text-[10px] font-black text-slate-400 uppercase tracking-widest"},"Filter by Work Order",-1)),p(q,{modelValue:a.value["work_order.id"].value,"onUpdate:modelValue":s[0]||(s[0]=o=>a.value["work_order.id"].value=o),options:[{order_no:"All Orders",id:null},...t.workOrders],optionLabel:"order_no",optionValue:"id",placeholder:"Select Order",class:"!h-9 !text-xs !rounded-lg"},null,8,["modelValue","options"])]),d("div",Ee,[s[9]||(s[9]=d("label",{class:"text-[10px] font-black text-slate-400 uppercase tracking-widest"},"Filter by Customer",-1)),p(q,{modelValue:a.value["work_order.customer.id"].value,"onUpdate:modelValue":s[1]||(s[1]=o=>a.value["work_order.customer.id"].value=o),options:[{legal_name:"All Customers",id:null},...t.customers],optionLabel:"legal_name",optionValue:"id",placeholder:"Select Customer",class:"!h-9 !text-xs !rounded-lg",filter:""},null,8,["modelValue","options"])])]),expansion:f(o=>[d("div",Ze,[p(m(Y),{class:"compact-tabs"},{default:f(()=>[p(m(Q),{header:"Batching"},{default:f(()=>[d("div",Ge,[p(de,{batch:o.data,workOrders:t.workOrders,trucks:t.trucks,transporters:t.transporters,personnel:t.personnel,products:t.products,uoms:t.uoms,statuses:t.statuses,onSaved:O,onCancel:O},null,8,["batch","workOrders","trucks","transporters","personnel","products","uoms","statuses"])])]),_:2},1024),p(m(Q),{header:"Dispatching"},{default:f(()=>[d("div",Je,[p(ce,{batch:o.data,workOrder:o.data.work_order,dropdownData:n.value,settings:t.batchingSettings,onSaved:O,onCancel:O},null,8,["batch","workOrder","dropdownData","settings"])])]),_:2},1024)]),_:2},1024)])]),default:f(()=>[p(m(x),{field:"empty_time",header:"Empty Time",sortable:""},{body:f(o=>[o.data.empty_time?(b(),g("div",Le,[d("span",Ne,y(new Date(o.data.empty_time).toLocaleDateString("en-IN",{day:"2-digit",month:"2-digit",year:"numeric"})),1),d("span",Ve,y(new Date(o.data.empty_time).toLocaleTimeString("en-IN",{hour:"2-digit",minute:"2-digit"})),1)])):(b(),g("span",Fe,"N/A"))]),_:1}),p(m(x),{field:"batch_no",header:"Order / Batch",sortable:""},{body:f(o=>{var h;return[d("div",null,[d("button",{class:"text-indigo-700 font-inter text-sm font-semibold hover:underline",type:"button",onClick:N(T=>P(o.data),["stop"])}," B"+y(o.data.batch_no),9,je),d("div",$e,y(((h=o.data.work_order)==null?void 0:h.order_no)||"-"),1)])]}),_:1}),p(m(x),{field:"work_order.customer.legal_name",header:"Customer",sortable:""},{body:f(o=>{var h,T,A,C;return[d("div",Re,[d("span",Ue,y(((T=(h=o.data.work_order)==null?void 0:h.customer)==null?void 0:T.legal_name)||"-"),1),d("span",We,y(((C=(A=o.data.work_order)==null?void 0:A.site)==null?void 0:C.name)||"Main Site"),1)])]}),_:1}),p(m(x),{field:"work_order.mix_design.design_name",header:"Design",sortable:""},{body:f(o=>{var h,T,A,C;return[d("div",ze,[d("span",Me,y(((T=(h=o.data.work_order)==null?void 0:h.mix_design)==null?void 0:T.design_name)||"-"),1),d("span",qe,y(((C=(A=o.data.work_order)==null?void 0:A.mix_design)==null?void 0:C.design_code)||"-"),1)])]}),_:1}),p(m(x),{field:"truck.registration",header:"Truck",sortable:""},{body:f(o=>{var h;return[d("span",Qe,y(((h=o.data.truck)==null?void 0:h.registration)||"-"),1)]}),_:1}),p(m(x),{field:"batch_size",header:"Batch Size",sortable:""},{body:f(o=>[d("span",Xe,y(o.data.batch_size)+" m³",1)]),_:1}),p(m(x),{field:"status",header:"Status",sortable:""},{body:f(o=>[p(m(pe),{value:G(o.data.status),severity:Z(o.data.status),rounded:""},null,8,["value","severity"])]),_:1}),p(m(x),{header:"Actions"},{body:f(o=>[d("div",Ye,[p(F,{icon:"pi pi-eye",severity:"secondary",variant:"text",rounded:"",onClick:N(h=>m(W).get(u.route("batches.report",o.data.id)),["stop"]),title:"Preview Batch Sheet"},null,8,["onClick"]),p(F,{icon:"pi pi-download",severity:"info",variant:"text",rounded:"",onClick:N(h=>ee(o.data.id),["stop"]),title:"Download PDF"},null,8,["onClick"]),o.data.status<3?(b(),_(F,{key:0,icon:"pi pi-trash",severity:"danger",variant:"text",rounded:"",onClick:N(h=>J(o.data),["stop"]),title:"Delete"},null,8,["onClick"])):k("",!0)])]),_:1})]),_:1},8,["value","first","rows","filters","expandedRows","dateFrom","dateTo"])])])])]),_:1}))}});export{un as default};
