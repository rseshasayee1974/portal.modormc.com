import{a as A,s as x}from"./index-B9fkz33_.js";import{s as v}from"./index-DwFXOek0.js";import{s as D}from"./index-S6qahblm.js";import{s as G}from"./index-BSXUPy3A.js";import{a as k}from"./index-CVX1OX7t.js";import{M as B,o as l,d as u,r as f,L as S,p as z,u as L,n as $,f as c,a as s,t as P,b as p,i as a,w as r,aa as M,a7 as O,aY as T,c as R}from"./app-Bbi1Q1_2.js";import{B as j}from"./BaseDeleteButton-D1FhgJpA.js";import{r as K}from"./MagnifyingGlassIcon-fBvOqgz0.js";import{_ as N}from"./_plugin-vue_export-helper-DlAUqK2U.js";var H=`
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
`,Y={root:"p-inputgroup"},q=B.extend({name:"inputgroup",style:H,classes:Y}),J={name:"BaseInputGroup",extends:k,style:q,provide:function(){return{$pcInputGroup:this,$parentInstance:this}}},I={name:"InputGroup",extends:J,inheritAttrs:!1};function Q(e,w,i,g,m,h){return l(),u("div",S({class:e.cx("root")},e.ptmi("root")),[f(e.$slots,"default")],16)}I.render=Q;var W={root:"p-inputgroupaddon"},X=B.extend({name:"inputgroupaddon",classes:W}),Z={name:"BaseInputGroupAddon",extends:k,style:X,provide:function(){return{$pcInputGroupAddon:this,$parentInstance:this}}},F={name:"InputGroupAddon",extends:Z,inheritAttrs:!1};function _(e,w,i,g,m,h){return l(),u("div",S({class:e.cx("root")},e.ptmi("root")),[f(e.$slots,"default")],16)}F.render=_;const ee={class:"base-datatable-wrapper border border-slate-200 rounded-sm overflow-hidden shadow-sm"},te={key:0,class:"bg-slate-50/30 border-b border-slate-100 px-4 py-2.5 flex items-center justify-between"},ne={key:0,class:"mr-auto flex items-center gap-2"},oe={class:"text-lg font-semibold text-slate-800"},ae={class:"flex items-end justify-end gap-2"},pe={class:"p-1 rounded-md bg-slate-50 group-focus-within:bg-indigo-50 transition-colors"},re={class:"font-semibold bg-gray-200/10 h-9 pt-2 rounded-3xl shadow-inner text-center text-slate-600 w-9 mx-auto"},ie={class:"flex justify-end"},le={class:"p-4 space-y-3"},de=z({__name:"BaseDataTable",props:{value:{},loading:{type:Boolean,default:!1},dataKey:{},expandedRows:{},first:{default:0},paginator:{type:Boolean,default:!0},rows:{default:10},totalRecords:{},rowsPerPageOptions:{default:()=>[30,50,100,200]},paginatorPosition:{default:"bottom"},lazy:{type:Boolean,default:!1},filters:{},globalFilterFields:{},filterMode:{default:"lenient"},filterDisplay:{default:"menu"},stripedRows:{type:Boolean,default:!0},removableSort:{type:Boolean,default:!1},responsiveLayout:{default:"scroll"},class:{},showSerial:{type:Boolean,default:!1},showSearch:{type:Boolean,default:!1},heading:{default:""},headingIcon:{default:""},deleteUrl:{},deleteTitle:{},deleteText:{}},emits:["update:first","update:rows","update:filters","page","sort","filter","row-click","rowExpand","rowCollapse","update:expandedRows"],setup(e,{emit:w}){const i=e,g=w,m=L(),h=o=>(i.first||0)+o+1,V=o=>{if(g("row-click",o),m.expansion){const n=o.originalEvent.target,t=n.closest(".p-row-toggler"),b=n.closest("button")||n.closest("a");if(!t&&!b){const y=o.data[i.dataKey||"id"],d=i.expandedRows?{...i.expandedRows}:{};d[y]?delete d[y]:(Object.keys(d).forEach(U=>delete d[U]),d[y]=!0),g("update:expandedRows",d)}}},C=[{label:"30 Per Page",value:30},{label:"50 Per Page",value:50},{label:"100 Per Page",value:100},{label:"200 Per Page",value:200}],E=o=>{const n={...i.filters};n.global||(n.global={value:null,matchMode:"contains"}),n.global.value=o,g("update:filters",n)};return(o,n)=>(l(),u("div",ee,[e.showSearch?(l(),u("div",te,[e.heading?(l(),u("div",ne,[e.headingIcon?(l(),u("i",{key:0,class:$([e.headingIcon,"text-xl text-indigo-500"])},null,2)):c("",!0),s("h3",oe,P(e.heading),1)])):c("",!0),s("div",ae,[p(a(D),{modelValue:e.rows,"onUpdate:modelValue":n[0]||(n[0]=t=>o.$emit("update:rows",t)),options:C,optionLabel:"label",optionValue:"value",placeholder:"Entries",class:"!h-10 !text-[11px] pt-1 !font-bold !bg-white !border-slate-200 !rounded-lg w-32 shadow-sm"},null,8,["modelValue"]),p(a(I),{class:"!rounded-md overflow-hidden border border-slate-200 shadow-sm group focus-within:border-indigo-400 transition-all bg-white h-10",style:{width:"200px"}},{default:r(()=>{var t,b;return[p(a(G),{modelValue:(b=(t=e.filters)==null?void 0:t.global)==null?void 0:b.value,"onUpdate:modelValue":E,placeholder:"Search records...",class:"!border-none !text-[13px] !font-bold !bg-transparent !px-3 h-full placeholder:text-slate-300"},null,8,["modelValue"]),p(a(F),{class:"!bg-transparent !border-none !px-2"},{default:r(()=>[s("div",pe,[p(a(K),{class:"w-3 h-3 text-slate-400 group-focus-within:text-indigo-500"})])]),_:1})]}),_:1})])])):c("",!0),p(a(A),{value:e.value,dataKey:e.dataKey||"id",loading:e.loading,lazy:e.lazy,paginator:e.paginator,first:e.first,rows:e.rows,totalRecords:e.totalRecords,rowsPerPageOptions:e.rowsPerPageOptions,paginatorPosition:e.paginatorPosition,filters:e.filters,globalFilterFields:e.globalFilterFields,filterMode:e.filterMode,filterDisplay:e.filterDisplay,stripedRows:e.stripedRows,removableSort:e.removableSort,responsiveLayout:e.responsiveLayout,expandedRows:e.expandedRows,"onUpdate:first":n[1]||(n[1]=t=>o.$emit("update:first",t)),"onUpdate:rows":n[2]||(n[2]=t=>o.$emit("update:rows",t)),"onUpdate:filters":n[3]||(n[3]=t=>o.$emit("update:filters",t)),"onUpdate:expandedRows":n[4]||(n[4]=t=>o.$emit("update:expandedRows",t)),class:$(["p-datatable-sm",[i.class,{"cursor-pointer":a(m).expansion}]]),rowHover:"",onPage:n[5]||(n[5]=t=>o.$emit("page",t)),onSort:n[6]||(n[6]=t=>o.$emit("sort",t)),onFilter:n[7]||(n[7]=t=>o.$emit("filter",t)),onRowClick:V,onRowExpand:n[8]||(n[8]=t=>o.$emit("rowExpand",t)),onRowCollapse:n[9]||(n[9]=t=>o.$emit("rowCollapse",t))},M({default:r(()=>[e.showSerial?(l(),R(a(x),{key:0,header:"S.No",style:{width:"5rem"}},{body:r(t=>[s("div",re,P(h(t.index)),1)]),_:1})):c("",!0),f(o.$slots,"default",{},void 0,!0),e.deleteUrl?(l(),R(a(x),{key:1,header:"Actions",style:{width:"70px","text-align":"right"}},{body:r(t=>[s("div",ie,[p(j,{url:e.deleteUrl(t.data),title:e.deleteTitle,text:e.deleteText},null,8,["url","title","text"])])]),_:1})):c("",!0)]),_:2},[o.$slots.header?{name:"header",fn:r(()=>[f(o.$slots,"header",{},void 0,!0)]),key:"0"}:void 0,e.loading?{name:"loading",fn:r(()=>[s("div",le,[p(a(v),{height:"1.5rem"}),p(a(v),{height:"1.5rem"}),p(a(v),{height:"1.5rem"})])]),key:"1"}:void 0,o.$slots.empty?{name:"empty",fn:r(()=>[f(o.$slots,"empty",{},void 0,!0)]),key:"2"}:void 0,o.$slots.expansion?{name:"expansion",fn:r(t=>[f(o.$slots,"expansion",O(T(t)),void 0,!0)]),key:"3"}:void 0]),1032,["value","dataKey","loading","lazy","paginator","first","rows","totalRecords","rowsPerPageOptions","paginatorPosition","filters","globalFilterFields","filterMode","filterDisplay","stripedRows","removableSort","responsiveLayout","expandedRows","class"])]))}}),ye=N(de,[["__scopeId","data-v-09631c5c"]]);export{ye as B};
