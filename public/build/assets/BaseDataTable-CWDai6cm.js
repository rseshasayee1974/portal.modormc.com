import{a as C,s as x}from"./index-CrRzexAu.js";import{s as y}from"./index-DJbNPFEk.js";import{s as E}from"./index-CPvxXCJt.js";import{s as U}from"./index-CYgV8cy1.js";import{a as P}from"./index-vVErtHBV.js";import{B as R,o as d,d as m,r as s,A as B,k as D,aa as G,b as a,e as p,w as r,a as c,h as v,n as z,a9 as O,a6 as T,aY as K,c as $,t as L}from"./app-B1Xz-Nfc.js";import{B as M}from"./BaseDeleteButton-eusbUeqK.js";import{r as j}from"./MagnifyingGlassIcon-kQWUTDZ8.js";import{_ as N}from"./_plugin-vue_export-helper-DlAUqK2U.js";var H=`
    .p-inputgroup,
    .p-inputgroup .p-iconfield,
    .p-inputgroup .p-floatlabel,
    .p-inputgroup .p-iftalabel {
        display: flex;
        align-items: stretch;
        width: 100%;
    }

    .p-inputgroup .p-floatlabel .p-inputwrapper,
    .p-inputgroup .p-iftalabel .p-inputwrapper {
        display: inline-flex;
    }

    .p-inputgroup .p-inputtext,
    .p-inputgroup .p-inputwrapper {
        flex: 1 1 auto;
        width: 1%;
    }

    .p-inputgroupaddon {
        display: flex;
        align-items: center;
        justify-content: center;
        padding: dt('inputgroup.addon.padding');
        background: dt('inputgroup.addon.background');
        color: dt('inputgroup.addon.color');
        border-block-start: 1px solid dt('inputgroup.addon.border.color');
        border-block-end: 1px solid dt('inputgroup.addon.border.color');
        min-width: dt('inputgroup.addon.min.width');
    }

    .p-inputgroupaddon:first-child,
    .p-inputgroupaddon + .p-inputgroupaddon {
        border-inline-start: 1px solid dt('inputgroup.addon.border.color');
    }

    .p-inputgroupaddon:last-child {
        border-inline-end: 1px solid dt('inputgroup.addon.border.color');
    }

    .p-inputgroupaddon:has(.p-button) {
        padding: 0;
        overflow: hidden;
    }

    .p-inputgroupaddon .p-button {
        border-radius: 0;
    }

    .p-inputgroup > .p-component,
    .p-inputgroup > .p-inputwrapper > .p-component,
    .p-inputgroup > .p-iconfield > .p-component,
    .p-inputgroup > .p-floatlabel > .p-component,
    .p-inputgroup > .p-floatlabel > .p-inputwrapper > .p-component,
    .p-inputgroup > .p-iftalabel > .p-component,
    .p-inputgroup > .p-iftalabel > .p-inputwrapper > .p-component {
        border-radius: 0;
        margin: 0;
    }

    .p-inputgroupaddon:first-child,
    .p-inputgroup > .p-component:first-child,
    .p-inputgroup > .p-inputwrapper:first-child > .p-component,
    .p-inputgroup > .p-iconfield:first-child > .p-component,
    .p-inputgroup > .p-floatlabel:first-child > .p-component,
    .p-inputgroup > .p-floatlabel:first-child > .p-inputwrapper > .p-component,
    .p-inputgroup > .p-iftalabel:first-child > .p-component,
    .p-inputgroup > .p-iftalabel:first-child > .p-inputwrapper > .p-component {
        border-start-start-radius: dt('inputgroup.addon.border.radius');
        border-end-start-radius: dt('inputgroup.addon.border.radius');
    }

    .p-inputgroupaddon:last-child,
    .p-inputgroup > .p-component:last-child,
    .p-inputgroup > .p-inputwrapper:last-child > .p-component,
    .p-inputgroup > .p-iconfield:last-child > .p-component,
    .p-inputgroup > .p-floatlabel:last-child > .p-component,
    .p-inputgroup > .p-floatlabel:last-child > .p-inputwrapper > .p-component,
    .p-inputgroup > .p-iftalabel:last-child > .p-component,
    .p-inputgroup > .p-iftalabel:last-child > .p-inputwrapper > .p-component {
        border-start-end-radius: dt('inputgroup.addon.border.radius');
        border-end-end-radius: dt('inputgroup.addon.border.radius');
    }

    .p-inputgroup .p-component:focus,
    .p-inputgroup .p-component.p-focus,
    .p-inputgroup .p-inputwrapper-focus,
    .p-inputgroup .p-component:focus ~ label,
    .p-inputgroup .p-component.p-focus ~ label,
    .p-inputgroup .p-inputwrapper-focus ~ label,
    .p-inputgroup .p-floatlabel .p-inputwrapper ~ label,
    .p-inputgroup .p-iftalabel .p-inputwrapper ~ label {
        z-index: 1;
    }

    .p-inputgroup > .p-button:not(.p-button-icon-only) {
        width: auto;
    }

    .p-inputgroup .p-iconfield + .p-iconfield .p-inputtext {
        border-inline-start: 0;
    }
`,Y={root:"p-inputgroup"},q=R.extend({name:"inputgroup",style:H,classes:Y}),J={name:"BaseInputGroup",extends:P,style:q,provide:function(){return{$pcInputGroup:this,$parentInstance:this}}},k={name:"InputGroup",extends:J,inheritAttrs:!1};function Q(e,b,i,u,f,w){return d(),m("div",B({class:e.cx("root")},e.ptmi("root")),[s(e.$slots,"default")],16)}k.render=Q;var W={root:"p-inputgroupaddon"},X=R.extend({name:"inputgroupaddon",classes:W}),Z={name:"BaseInputGroupAddon",extends:P,style:X,provide:function(){return{$pcInputGroupAddon:this,$parentInstance:this}}},S={name:"InputGroupAddon",extends:Z,inheritAttrs:!1};function _(e,b,i,u,f,w){return d(),m("div",B({class:e.cx("root")},e.ptmi("root")),[s(e.$slots,"default")],16)}S.render=_;const ee={class:"base-datatable-wrapper border border-slate-200 rounded-sm overflow-hidden shadow-sm"},te={key:0,class:"bg-slate-50/30 border-b border-slate-100 px-4 py-2.5 flex items-center justify-end gap-2"},oe={class:"p-1 rounded-md bg-slate-50 group-focus-within:bg-indigo-50 transition-colors"},ne={class:"font-semibold bg-gray-200/10 h-9 pt-2 rounded-3xl shadow-inner text-center text-slate-600 w-9 mx-auto"},pe={class:"flex justify-end"},ae={class:"p-4 space-y-3"},re=D({__name:"BaseDataTable",props:{value:{},loading:{type:Boolean,default:!1},dataKey:{},expandedRows:{},first:{default:0},paginator:{type:Boolean,default:!0},rows:{default:10},totalRecords:{},rowsPerPageOptions:{default:()=>[30,50,100,200]},paginatorPosition:{default:"bottom"},lazy:{type:Boolean,default:!1},filters:{},globalFilterFields:{},filterMode:{default:"lenient"},filterDisplay:{default:"menu"},stripedRows:{type:Boolean,default:!0},removableSort:{type:Boolean,default:!1},responsiveLayout:{default:"scroll"},class:{},showSerial:{type:Boolean,default:!1},showSearch:{type:Boolean,default:!1},deleteUrl:{},deleteTitle:{},deleteText:{}},emits:["update:first","update:rows","update:filters","page","sort","filter","row-click","rowExpand","rowCollapse","update:expandedRows"],setup(e,{emit:b}){const i=e,u=b,f=G(),w=n=>(i.first||0)+n+1,I=n=>{if(u("row-click",n),f.expansion){const o=n.originalEvent.target,t=o.closest(".p-row-toggler"),g=o.closest("button")||o.closest("a");if(!t&&!g){const h=n.data[i.dataKey||"id"],l=i.expandedRows?{...i.expandedRows}:{};l[h]?delete l[h]:(Object.keys(l).forEach(A=>delete l[A]),l[h]=!0),u("update:expandedRows",l)}}},F=[{label:"30 Per Page",value:30},{label:"50 Per Page",value:50},{label:"100 Per Page",value:100},{label:"200 Per Page",value:200}],V=n=>{const o={...i.filters};o.global||(o.global={value:null,matchMode:"contains"}),o.global.value=n,u("update:filters",o)};return(n,o)=>(d(),m("div",ee,[e.showSearch?(d(),m("div",te,[a(p(E),{modelValue:e.rows,"onUpdate:modelValue":o[0]||(o[0]=t=>n.$emit("update:rows",t)),options:F,optionLabel:"label",optionValue:"value",placeholder:"Entries",class:"h-10 !text-[11px] !font-bold !bg-white !border-slate-200 !rounded-lg w-32 shadow-sm"},null,8,["modelValue"]),a(p(k),{class:"!rounded-md overflow-hidden border border-slate-200 shadow-sm group focus-within:border-indigo-400 transition-all bg-white h-10",style:{width:"200px"}},{default:r(()=>{var t,g;return[a(p(U),{modelValue:(g=(t=e.filters)==null?void 0:t.global)==null?void 0:g.value,"onUpdate:modelValue":V,placeholder:"Search records...",class:"!border-none !text-[13px] !font-bold !bg-transparent !px-3 h-full placeholder:text-slate-300"},null,8,["modelValue"]),a(p(S),{class:"!bg-transparent !border-none !px-2"},{default:r(()=>[c("div",oe,[a(p(j),{class:"w-3 h-3 text-slate-400 group-focus-within:text-indigo-500"})])]),_:1})]}),_:1})])):v("",!0),a(p(C),{value:e.value,dataKey:e.dataKey||"id",loading:e.loading,lazy:e.lazy,paginator:e.paginator,first:e.first,rows:e.rows,totalRecords:e.totalRecords,rowsPerPageOptions:e.rowsPerPageOptions,paginatorPosition:e.paginatorPosition,filters:e.filters,globalFilterFields:e.globalFilterFields,filterMode:e.filterMode,filterDisplay:e.filterDisplay,stripedRows:e.stripedRows,removableSort:e.removableSort,responsiveLayout:e.responsiveLayout,expandedRows:e.expandedRows,"onUpdate:first":o[1]||(o[1]=t=>n.$emit("update:first",t)),"onUpdate:rows":o[2]||(o[2]=t=>n.$emit("update:rows",t)),"onUpdate:filters":o[3]||(o[3]=t=>n.$emit("update:filters",t)),"onUpdate:expandedRows":o[4]||(o[4]=t=>n.$emit("update:expandedRows",t)),class:z(["p-datatable-sm",[i.class,{"cursor-pointer":p(f).expansion}]]),rowHover:"",onPage:o[5]||(o[5]=t=>n.$emit("page",t)),onSort:o[6]||(o[6]=t=>n.$emit("sort",t)),onFilter:o[7]||(o[7]=t=>n.$emit("filter",t)),onRowClick:I,onRowExpand:o[8]||(o[8]=t=>n.$emit("rowExpand",t)),onRowCollapse:o[9]||(o[9]=t=>n.$emit("rowCollapse",t))},O({default:r(()=>[e.showSerial?(d(),$(p(x),{key:0,header:"S.No",style:{width:"5rem"}},{body:r(t=>[c("div",ne,L(w(t.index)),1)]),_:1})):v("",!0),s(n.$slots,"default",{},void 0,!0),e.deleteUrl?(d(),$(p(x),{key:1,header:"Actions",style:{width:"70px","text-align":"right"}},{body:r(t=>[c("div",pe,[a(M,{url:e.deleteUrl(t.data),title:e.deleteTitle,text:e.deleteText},null,8,["url","title","text"])])]),_:1})):v("",!0)]),_:2},[n.$slots.header?{name:"header",fn:r(()=>[s(n.$slots,"header",{},void 0,!0)]),key:"0"}:void 0,e.loading?{name:"loading",fn:r(()=>[c("div",ae,[a(p(y),{height:"1.5rem"}),a(p(y),{height:"1.5rem"}),a(p(y),{height:"1.5rem"})])]),key:"1"}:void 0,n.$slots.empty?{name:"empty",fn:r(()=>[s(n.$slots,"empty",{},void 0,!0)]),key:"2"}:void 0,n.$slots.expansion?{name:"expansion",fn:r(t=>[s(n.$slots,"expansion",T(K(t)),void 0,!0)]),key:"3"}:void 0]),1032,["value","dataKey","loading","lazy","paginator","first","rows","totalRecords","rowsPerPageOptions","paginatorPosition","filters","globalFilterFields","filterMode","filterDisplay","stripedRows","removableSort","responsiveLayout","expandedRows","class"])]))}}),be=N(re,[["__scopeId","data-v-b82ea765"]]);export{be as B};
