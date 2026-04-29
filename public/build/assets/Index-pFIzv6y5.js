import{M,W as P,X as E,Z as q,_ as S,$ as V,L as b,a0 as Q,o as p,d as g,a as l,I as D,r as L,c as I,U as K,f as T,F as H,k as N,t as x,a1 as X,p as Y,w as v,b as u,i as m,e as _,g as Z,l as O,E as G}from"./app-Bbi1Q1_2.js";import{S as j}from"./sweetalert2.esm.all-Cj9UHshf.js";import{_ as J}from"./AppLayout-DZ9qqeCh.js";import{M as ee}from"./ModuleSubTopNav--qtO0jOu.js";import{_ as te}from"./BatchCreateForm.vue_vue_type_script_setup_true_lang-BOkM-GDO.js";import{_ as ne}from"./BatchEditForm.vue_vue_type_script_setup_true_lang-BZnHI-t5.js";import ae from"./DispatchSection-Ban0uBVs.js";import{B as re}from"./BaseDataTable-CqNQRhuh.js";import{B as oe}from"./BaseInput-BIZPfrUA.js";import{_ as F}from"./BaseSelect.vue_vue_type_style_index_0_lang-CBN_JFKl.js";import{s as k}from"./index-B9fkz33_.js";import{s as ie}from"./index-_CQkPwPO.js";import{s as se}from"./index-DJC22RIl.js";import{s as le}from"./index-CddlwGxj.js";import{R as de,a as ce}from"./index-CVX1OX7t.js";import{s as R}from"./index-B-8to84N.js";import{_ as ue}from"./BaseButton.vue_vue_type_script_setup_true_lang-BpiTD1zA.js";import{r as pe}from"./ListBulletIcon-_cXqSmKM.js";import"./ApplicationMark-Cy74QR0Z.js";import"./_plugin-vue_export-helper-DlAUqK2U.js";import"./DropdownLink-D-ccT1UZ.js";import"./index-DRntNpK6.js";import"./index-BFg6Adjf.js";import"./index-CNqheahg.js";import"./index-fRl3PENQ.js";import"./index-DuXujspC.js";import"./index-DyVx-WO4.js";import"./ScaleIcon-CK2kvFWh.js";import"./UserPlusIcon-XkfMO7A0.js";import"./DocumentTextIcon-C6Ci2ggJ.js";import"./ClockIcon-CyodutTB.js";import"./ArrowUpRightIcon-Bh2LEmpx.js";import"./BriefcaseIcon-B6344OTQ.js";import"./IdentificationIcon-Cm0VwwGc.js";import"./ClipboardDocumentListIcon-Dt3A56Wx.js";import"./Cog6ToothIcon-BIgTDOrl.js";import"./BaseInputNumber.vue_vue_type_style_index_0_lang-4xYhBmKL.js";import"./index-CSmRY9ei.js";import"./index-BSXUPy3A.js";import"./BaseField.vue_vue_type_script_setup_true_lang-CELY5sEK.js";import"./BaseDatePicker.vue_vue_type_style_index_0_lang-CiARWFMq.js";import"./index-C4sNgrHq.js";import"./index-S6qahblm.js";import"./index-DP7I5pTp.js";import"./useWeighbridge-BaCN2c0n.js";import"./PlusCircleIcon-7O7kzaLi.js";import"./InformationCircleIcon-CKFQH6wZ.js";import"./ArrowDownTrayIcon-BwhbADuq.js";import"./BaseFormActions-6oUfKO-o.js";import"./CubeIcon-D3R7ugLt.js";import"./DispatchWeightsForm.vue_vue_type_script_setup_true_lang-Bs3Vm_Qp.js";import"./TruckIcon-BDUwjh9X.js";import"./BanknotesIcon-DXbukApF.js";import"./DispatchTransportForm.vue_vue_type_script_setup_true_lang-C4Y3HZCZ.js";import"./index-DwFXOek0.js";import"./BaseDeleteButton-D1FhgJpA.js";import"./TrashIcon-CK5s0OIo.js";import"./MagnifyingGlassIcon-fBvOqgz0.js";import"./index-D6a9eSfs.js";import"./index-awd1gAil.js";var be=`
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
`,fe={root:function(e){var n=e.props;return["p-tabview p-component",{"p-tabview-scrollable":n.scrollable}]},navContainer:"p-tabview-tablist-container",prevButton:"p-tabview-prev-button",navContent:"p-tabview-tablist-scroll-container",nav:"p-tabview-tablist",tab:{header:function(e){var n=e.instance,a=e.tab,i=e.index;return["p-tabview-tablist-item",n.getTabProp(a,"headerClass"),{"p-tabview-tablist-item-active":n.d_activeIndex===i,"p-disabled":n.getTabProp(a,"disabled")}]},headerAction:"p-tabview-tab-header",headerTitle:"p-tabview-tab-title",content:function(e){var n=e.instance,a=e.tab;return["p-tabview-panel",n.getTabProp(a,"contentClass")]}},inkbar:"p-tabview-ink-bar",nextButton:"p-tabview-next-button",panelContainer:"p-tabview-panels"},ve=M.extend({name:"tabview",style:be,classes:fe}),he={name:"BaseTabView",extends:ce,props:{activeIndex:{type:Number,default:0},lazy:{type:Boolean,default:!1},scrollable:{type:Boolean,default:!1},tabindex:{type:Number,default:0},selectOnFocus:{type:Boolean,default:!1},prevButtonProps:{type:null,default:null},nextButtonProps:{type:null,default:null},prevIcon:{type:String,default:void 0},nextIcon:{type:String,default:void 0}},style:ve,provide:function(){return{$pcTabs:void 0,$pcTabView:this,$parentInstance:this}}},U={name:"TabView",extends:he,inheritAttrs:!1,emits:["update:activeIndex","tab-change","tab-click"],data:function(){return{d_activeIndex:this.activeIndex,isPrevButtonDisabled:!0,isNextButtonDisabled:!1}},watch:{activeIndex:function(e){this.d_activeIndex=e,this.scrollInView({index:e})}},mounted:function(){console.warn("Deprecated since v4. Use Tabs component instead."),this.updateInkBar(),this.scrollable&&this.updateButtonState()},updated:function(){this.updateInkBar(),this.scrollable&&this.updateButtonState()},methods:{isTabPanel:function(e){return e.type.name==="TabPanel"},isTabActive:function(e){return this.d_activeIndex===e},getTabProp:function(e,n){return e.props?e.props[n]:void 0},getKey:function(e,n){return this.getTabProp(e,"header")||n},getTabHeaderActionId:function(e){return"".concat(this.$id,"_").concat(e,"_header_action")},getTabContentId:function(e){return"".concat(this.$id,"_").concat(e,"_content")},getTabPT:function(e,n,a){var i=this.tabs.length,r={props:e.props,parent:{instance:this,props:this.$props,state:this.$data},context:{index:a,count:i,first:a===0,last:a===i-1,active:this.isTabActive(a)}};return b(this.ptm("tabpanel.".concat(n),{tabpanel:r}),this.ptm("tabpanel.".concat(n),r),this.ptmo(this.getTabProp(e,"pt"),n,r))},onScroll:function(e){this.scrollable&&this.updateButtonState(),e.preventDefault()},onPrevButtonClick:function(){var e=this.$refs.content,n=P(e),a=e.scrollLeft-n;e.scrollLeft=a<=0?0:a},onNextButtonClick:function(){var e=this.$refs.content,n=P(e)-this.getVisibleButtonWidths(),a=e.scrollLeft+n,i=e.scrollWidth-n;e.scrollLeft=a>=i?i:a},onTabClick:function(e,n,a){this.changeActiveIndex(e,n,a),this.$emit("tab-click",{originalEvent:e,index:a})},onTabKeyDown:function(e,n,a){switch(e.code){case"ArrowLeft":this.onTabArrowLeftKey(e);break;case"ArrowRight":this.onTabArrowRightKey(e);break;case"Home":this.onTabHomeKey(e);break;case"End":this.onTabEndKey(e);break;case"PageDown":this.onPageDownKey(e);break;case"PageUp":this.onPageUpKey(e);break;case"Enter":case"NumpadEnter":case"Space":this.onTabEnterKey(e,n,a);break}},onTabArrowRightKey:function(e){var n=this.findNextHeaderAction(e.target.parentElement);n?this.changeFocusedTab(e,n):this.onTabHomeKey(e),e.preventDefault()},onTabArrowLeftKey:function(e){var n=this.findPrevHeaderAction(e.target.parentElement);n?this.changeFocusedTab(e,n):this.onTabEndKey(e),e.preventDefault()},onTabHomeKey:function(e){var n=this.findFirstHeaderAction();this.changeFocusedTab(e,n),e.preventDefault()},onTabEndKey:function(e){var n=this.findLastHeaderAction();this.changeFocusedTab(e,n),e.preventDefault()},onPageDownKey:function(e){this.scrollInView({index:this.$refs.nav.children.length-2}),e.preventDefault()},onPageUpKey:function(e){this.scrollInView({index:0}),e.preventDefault()},onTabEnterKey:function(e,n,a){this.changeActiveIndex(e,n,a),e.preventDefault()},findNextHeaderAction:function(e){var n=arguments.length>1&&arguments[1]!==void 0?arguments[1]:!1,a=n?e:e.nextElementSibling;return a?S(a,"data-p-disabled")||S(a,"data-pc-section")==="inkbar"?this.findNextHeaderAction(a):V(a,'[data-pc-section="headeraction"]'):null},findPrevHeaderAction:function(e){var n=arguments.length>1&&arguments[1]!==void 0?arguments[1]:!1,a=n?e:e.previousElementSibling;return a?S(a,"data-p-disabled")||S(a,"data-pc-section")==="inkbar"?this.findPrevHeaderAction(a):V(a,'[data-pc-section="headeraction"]'):null},findFirstHeaderAction:function(){return this.findNextHeaderAction(this.$refs.nav.firstElementChild,!0)},findLastHeaderAction:function(){return this.findPrevHeaderAction(this.$refs.nav.lastElementChild,!0)},changeActiveIndex:function(e,n,a){!this.getTabProp(n,"disabled")&&this.d_activeIndex!==a&&(this.d_activeIndex=a,this.$emit("update:activeIndex",a),this.$emit("tab-change",{originalEvent:e,index:a}),this.scrollInView({index:a}))},changeFocusedTab:function(e,n){if(n&&(q(n),this.scrollInView({element:n}),this.selectOnFocus)){var a=parseInt(n.parentElement.dataset.pcIndex,10),i=this.tabs[a];this.changeActiveIndex(e,i,a)}},scrollInView:function(e){var n=e.element,a=e.index,i=a===void 0?-1:a,r=n||this.$refs.nav.children[i];r&&r.scrollIntoView&&r.scrollIntoView({block:"nearest"})},updateInkBar:function(){var e=this.$refs.nav.children[this.d_activeIndex];this.$refs.inkbar.style.width=P(e)+"px",this.$refs.inkbar.style.left=E(e).left-E(this.$refs.nav).left+"px"},updateButtonState:function(){var e=this.$refs.content,n=e.scrollLeft,a=e.scrollWidth,i=P(e);this.isPrevButtonDisabled=n===0,this.isNextButtonDisabled=parseInt(n)===a-i},getVisibleButtonWidths:function(){var e=this.$refs,n=e.prevBtn,a=e.nextBtn;return[n,a].reduce(function(i,r){return r?i+P(r):i},0)}},computed:{tabs:function(){var e=this;return this.$slots.default().reduce(function(n,a){return e.isTabPanel(a)?n.push(a):a.children&&a.children instanceof Array&&a.children.forEach(function(i){e.isTabPanel(i)&&n.push(i)}),n},[])},prevButtonAriaLabel:function(){return this.$primevue.config.locale.aria?this.$primevue.config.locale.aria.previous:void 0},nextButtonAriaLabel:function(){return this.$primevue.config.locale.aria?this.$primevue.config.locale.aria.next:void 0}},directives:{ripple:de},components:{ChevronLeftIcon:se,ChevronRightIcon:le}};function A(t){"@babel/helpers - typeof";return A=typeof Symbol=="function"&&typeof Symbol.iterator=="symbol"?function(e){return typeof e}:function(e){return e&&typeof Symbol=="function"&&e.constructor===Symbol&&e!==Symbol.prototype?"symbol":typeof e},A(t)}function $(t,e){var n=Object.keys(t);if(Object.getOwnPropertySymbols){var a=Object.getOwnPropertySymbols(t);e&&(a=a.filter(function(i){return Object.getOwnPropertyDescriptor(t,i).enumerable})),n.push.apply(n,a)}return n}function h(t){for(var e=1;e<arguments.length;e++){var n=arguments[e]!=null?arguments[e]:{};e%2?$(Object(n),!0).forEach(function(a){me(t,a,n[a])}):Object.getOwnPropertyDescriptors?Object.defineProperties(t,Object.getOwnPropertyDescriptors(n)):$(Object(n)).forEach(function(a){Object.defineProperty(t,a,Object.getOwnPropertyDescriptor(n,a))})}return t}function me(t,e,n){return(e=ge(e))in t?Object.defineProperty(t,e,{value:n,enumerable:!0,configurable:!0,writable:!0}):t[e]=n,t}function ge(t){var e=we(t,"string");return A(e)=="symbol"?e:e+""}function we(t,e){if(A(t)!="object"||!t)return t;var n=t[Symbol.toPrimitive];if(n!==void 0){var a=n.call(t,e);if(A(a)!="object")return a;throw new TypeError("@@toPrimitive must return a primitive value.")}return(e==="string"?String:Number)(t)}var ye=["tabindex","aria-label"],xe=["data-p-active","data-p-disabled","data-pc-index"],ke=["id","tabindex","aria-disabled","aria-selected","aria-controls","onClick","onKeydown"],Te=["tabindex","aria-label"],Be=["id","aria-labelledby","data-pc-index","data-p-active"];function Pe(t,e,n,a,i,r){var y=Q("ripple");return p(),g("div",b({class:t.cx("root"),role:"tablist"},t.ptmi("root")),[l("div",b({class:t.cx("navContainer")},t.ptm("navContainer")),[t.scrollable&&!i.isPrevButtonDisabled?D((p(),g("button",b({key:0,ref:"prevBtn",type:"button",class:t.cx("prevButton"),tabindex:t.tabindex,"aria-label":r.prevButtonAriaLabel,onClick:e[0]||(e[0]=function(){return r.onPrevButtonClick&&r.onPrevButtonClick.apply(r,arguments)})},h(h({},t.prevButtonProps),t.ptm("prevButton")),{"data-pc-group-section":"navbutton"}),[L(t.$slots,"previcon",{},function(){return[(p(),I(K(t.prevIcon?"span":"ChevronLeftIcon"),b({"aria-hidden":"true",class:t.prevIcon},t.ptm("prevIcon")),null,16,["class"]))]})],16,ye)),[[y]]):T("",!0),l("div",b({ref:"content",class:t.cx("navContent"),onScroll:e[1]||(e[1]=function(){return r.onScroll&&r.onScroll.apply(r,arguments)})},t.ptm("navContent")),[l("ul",b({ref:"nav",class:t.cx("nav")},t.ptm("nav")),[(p(!0),g(H,null,N(r.tabs,function(s,d){return p(),g("li",b({key:r.getKey(s,d),style:r.getTabProp(s,"headerStyle"),class:t.cx("tab.header",{tab:s,index:d}),role:"presentation"},{ref_for:!0},h(h(h({},r.getTabProp(s,"headerProps")),r.getTabPT(s,"root",d)),r.getTabPT(s,"header",d)),{"data-pc-name":"tabpanel","data-p-active":i.d_activeIndex===d,"data-p-disabled":r.getTabProp(s,"disabled"),"data-pc-index":d}),[D((p(),g("a",b({id:r.getTabHeaderActionId(d),class:t.cx("tab.headerAction"),tabindex:r.getTabProp(s,"disabled")||!r.isTabActive(d)?-1:t.tabindex,role:"tab","aria-disabled":r.getTabProp(s,"disabled"),"aria-selected":r.isTabActive(d),"aria-controls":r.getTabContentId(d),onClick:function(B){return r.onTabClick(B,s,d)},onKeydown:function(B){return r.onTabKeyDown(B,s,d)}},{ref_for:!0},h(h({},r.getTabProp(s,"headerActionProps")),r.getTabPT(s,"headerAction",d))),[s.props&&s.props.header?(p(),g("span",b({key:0,class:t.cx("tab.headerTitle")},{ref_for:!0},r.getTabPT(s,"headerTitle",d)),x(s.props.header),17)):T("",!0),s.children&&s.children.header?(p(),I(K(s.children.header),{key:1})):T("",!0)],16,ke)),[[y]])],16,xe)}),128)),l("li",b({ref:"inkbar",class:t.cx("inkbar"),role:"presentation","aria-hidden":"true"},t.ptm("inkbar")),null,16)],16)],16),t.scrollable&&!i.isNextButtonDisabled?D((p(),g("button",b({key:1,ref:"nextBtn",type:"button",class:t.cx("nextButton"),tabindex:t.tabindex,"aria-label":r.nextButtonAriaLabel,onClick:e[2]||(e[2]=function(){return r.onNextButtonClick&&r.onNextButtonClick.apply(r,arguments)})},h(h({},t.nextButtonProps),t.ptm("nextButton")),{"data-pc-group-section":"navbutton"}),[L(t.$slots,"nexticon",{},function(){return[(p(),I(K(t.nextIcon?"span":"ChevronRightIcon"),b({"aria-hidden":"true",class:t.nextIcon},t.ptm("nextIcon")),null,16,["class"]))]})],16,Te)),[[y]]):T("",!0)],16),l("div",b({class:t.cx("panelContainer")},t.ptm("panelContainer")),[(p(!0),g(H,null,N(r.tabs,function(s,d){return p(),g(H,{key:r.getKey(s,d)},[!t.lazy||r.isTabActive(d)?D((p(),g("div",b({key:0,id:r.getTabContentId(d),style:r.getTabProp(s,"contentStyle"),class:t.cx("tab.content",{tab:s}),role:"tabpanel","aria-labelledby":r.getTabHeaderActionId(d)},{ref_for:!0},h(h(h({},r.getTabProp(s,"contentProps")),r.getTabPT(s,"root",d)),r.getTabPT(s,"content",d)),{"data-pc-name":"tabpanel","data-pc-index":d,"data-p-active":i.d_activeIndex===d}),[(p(),I(K(s)))],16,Be)),[[X,t.lazy?!0:r.isTabActive(d)]]):T("",!0)],64)}),128))],16)],16)}U.render=Pe;const Ie={class:"py-2 px-4"},Ae={class:"max-w-7xl mx-auto mt-4 space-y-4"},Ce={key:0,class:"rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs font-medium text-amber-700"},Se={class:"bg-white shadow-xl sm:rounded-lg p-8"},De={class:"flex flex-col md:flex-row justify-between items-start md:items-center mb-8 gap-4"},Ke={class:"flex items-center gap-4"},Oe={class:"w-10 h-10 rounded-lg bg-cyan-50 flex items-center justify-center shadow-sm border border-cyan-100/50"},He={class:"flex flex-col sm:flex-row gap-3 w-full md:w-auto"},Ee=["onClick"],Ve={class:"text-xs text-slate-500"},Le={class:"text-xs font-semibold text-slate-700"},Ne={class:"text-xs font-bold text-slate-700"},_e={class:"text-xs text-slate-500"},je={class:"flex justify-end gap-2"},Fe={class:"p-1 bg-slate-50/50 border-y border-slate-100"},Re={class:""},$e={class:""},Yt=Y({__name:"Index",props:{batches:{},workOrders:{},trucks:{},transporters:{},personnel:{},taxes:{},products:{},loading_sites:{},unloading_sites:{},uoms:{},statuses:{},schemaWarning:{},nextBatchNo:{}},setup(t){const e=t,n=Z(()=>({trucks:e.trucks,transporters:e.transporters,personnel:e.personnel,taxes:e.taxes,uoms:e.uoms,unloading_sites:e.unloading_sites,loading_sites:e.loading_sites})),a=O({global:{value:null,matchMode:"contains"},status:{value:null,matchMode:"equals"}}),i=O({}),r=O(0),y=O(30),s=[{label:"30",value:30},{label:"50",value:50},{label:"100",value:100}],d=f=>{i.value[f.id]?i.value={}:i.value={[f.id]:!0}},C=()=>{i.value={}},B=f=>f===4?"success":f===2||f===3?"info":f===5?"danger":"warn",z=f=>{var c;return((c=e.statuses.find(o=>o.value===f))==null?void 0:c.label)??"Unknown"},W=f=>{j.fire({title:"Delete batch?",text:`Batch #${f.batch_no} will be deleted.`,icon:"warning",showCancelButton:!0,confirmButtonColor:"#dc2626",confirmButtonText:"Yes, delete"}).then(c=>{c.isConfirmed&&G.delete(route("batches.destroy",f.id),{preserveScroll:!0,onSuccess:()=>{j.fire({toast:!0,position:"top-end",icon:"success",title:"Batch deleted successfully.",showConfirmButton:!1,timer:2500})}})})};return(f,c)=>(p(),I(J,{title:"Batches"},{default:v(()=>[l("div",Ie,[u(ee),l("div",Ae,[t.schemaWarning?(p(),g("div",Ce,x(t.schemaWarning),1)):T("",!0),u(te,{workOrders:t.workOrders,trucks:t.trucks,transporters:t.transporters,personnel:t.personnel,products:t.products,uoms:t.uoms,unloading_sites:t.unloading_sites,loading_sites:t.loading_sites,taxes:t.taxes,statuses:t.statuses,nextBatchNo:t.nextBatchNo},null,8,["workOrders","trucks","transporters","personnel","products","uoms","unloading_sites","loading_sites","taxes","statuses","nextBatchNo"]),c[8]||(c[8]=l("hr",{class:"border-slate-200 border-dashed"},null,-1)),l("div",Se,[l("div",De,[l("div",Ke,[l("div",Oe,[u(m(pe),{class:"w-5 h-5 text-cyan-600"})]),c[7]||(c[7]=l("div",null,[l("h3",{class:"text-md font-semibold text-slate-800 uppercase"},"List Of Batches"),l("div",{class:"flex items-center gap-2 mt-0.5"},[l("span",{class:"w-2 h-2 rounded-full bg-emerald-500"}),l("p",{class:"text-[10px] text-slate-400 font-black uppercase tracking-[0.2em] leading-none"},"Batch Execution Directory")])],-1))]),l("div",He,[u(F,{modelValue:y.value,"onUpdate:modelValue":c[0]||(c[0]=o=>y.value=o),options:s,optionLabel:"label",optionValue:"value",class:"!h-10 w-20 flex items-center justify-center font-bold text-xs",pt:{root:{class:"border border-slate-300 rounded-md"},label:{class:"text-xs p-2"}}},null,8,["modelValue"]),u(oe,{modelValue:a.value.global.value,"onUpdate:modelValue":c[1]||(c[1]=o=>a.value.global.value=o),placeholder:"Search order/batch/truck...",inputClass:"!h-9 !w-72"},null,8,["modelValue"]),u(F,{modelValue:a.value.status.value,"onUpdate:modelValue":c[2]||(c[2]=o=>a.value.status.value=o),options:[{label:"All Statuses",value:null},...t.statuses],optionLabel:"label",optionValue:"value",placeholder:"Filter status",class:"w-44"},null,8,["modelValue","options"])])]),u(re,{value:t.batches,first:r.value,"onUpdate:first":c[3]||(c[3]=o=>r.value=o),rows:y.value,"onUpdate:rows":c[4]||(c[4]=o=>y.value=o),filters:a.value,"onUpdate:filters":c[5]||(c[5]=o=>a.value=o),expandedRows:i.value,"onUpdate:expandedRows":c[6]||(c[6]=o=>i.value=o),dataKey:"id",paginator:"",stripedRows:"",removableSort:"",rowHover:"",filterDisplay:"menu",class:"cursor-pointer",globalFilterFields:["batch_no","work_order.order_no","truck.registration"],showSerial:"",showSearch:!1},{expansion:v(o=>[l("div",Fe,[u(m(U),{class:"compact-tabs"},{default:v(()=>[u(m(R),{header:"Batching"},{default:v(()=>[l("div",Re,[u(ne,{batch:o.data,workOrders:t.workOrders,trucks:t.trucks,transporters:t.transporters,personnel:t.personnel,products:t.products,uoms:t.uoms,statuses:t.statuses,onSaved:C,onCancel:C},null,8,["batch","workOrders","trucks","transporters","personnel","products","uoms","statuses"])])]),_:2},1024),u(m(R),{header:"Dispatching"},{default:v(()=>[l("div",$e,[u(ae,{batch:o.data,workOrder:o.data.work_order,dropdownData:n.value},null,8,["batch","workOrder","dropdownData"])])]),_:2},1024)]),_:2},1024)])]),default:v(()=>[u(m(k),{field:"batch_no",header:"Order / Batch",sortable:""},{body:v(o=>{var w;return[l("div",null,[l("button",{class:"text-indigo-700 font-inter text-sm font-semibold hover:underline",type:"button",onClick:_(Ue=>d(o.data),["stop"])}," B"+x(o.data.batch_no),9,Ee),l("div",Ve,x(((w=o.data.work_order)==null?void 0:w.order_no)||"-"),1)])]}),_:1}),u(m(k),{field:"truck.registration",header:"Truck",sortable:""},{body:v(o=>{var w;return[l("span",Le,x(((w=o.data.truck)==null?void 0:w.registration)||"-"),1)]}),_:1}),u(m(k),{field:"batch_size",header:"Batch Size",sortable:""},{body:v(o=>[l("span",Ne,x(o.data.batch_size)+" m³",1)]),_:1}),u(m(k),{header:"Materials"},{body:v(o=>{var w;return[l("span",_e,x(((w=o.data.materials)==null?void 0:w.length)||0)+" line(s)",1)]}),_:1}),u(m(k),{field:"status",header:"Status",sortable:""},{body:v(o=>[u(m(ie),{value:z(o.data.status),severity:B(o.data.status),rounded:""},null,8,["value","severity"])]),_:1}),u(m(k),{header:"Actions"},{body:v(o=>[l("div",je,[u(ue,{icon:"pi pi-trash",severity:"danger",variant:"text",rounded:"",onClick:_(w=>W(o.data),["stop"]),title:"Delete"},null,8,["onClick"])])]),_:1})]),_:1},8,["value","first","rows","filters","expandedRows"])])])])]),_:1}))}});export{Yt as default};
