import{M as U,ah as x,a0 as M,I as j,o as _,d as h,a as n,r as w,n as G,L as v,f as H,t as B,aC as O,aE as T,T as W,F as Y,k as J,c as Q,aa as X,w as Z,p as tt,h as et,j as y,b as g,i as r,e as ot,g as nt}from"./app-Bbi1Q1_2.js";import{_ as m}from"./BaseSelect.vue_vue_type_style_index_0_lang-CBN_JFKl.js";import{B as lt}from"./BaseInput-BIZPfrUA.js";import{_ as k}from"./BaseInputNumber.vue_vue_type_style_index_0_lang-4xYhBmKL.js";import{s as it}from"./index-C4sNgrHq.js";import{R as A,f as D}from"./index-CVX1OX7t.js";import{b as $}from"./index-BSXUPy3A.js";import{s as rt}from"./index-fRl3PENQ.js";import{_ as at}from"./_plugin-vue_export-helper-DlAUqK2U.js";import"./index-S6qahblm.js";import"./index-BFg6Adjf.js";import"./index-DRntNpK6.js";import"./index-DP7I5pTp.js";import"./BaseField.vue_vue_type_script_setup_true_lang-CELY5sEK.js";import"./index-CSmRY9ei.js";import"./index-DJC22RIl.js";import"./index-CddlwGxj.js";var st=`
    .p-togglebutton {
        display: inline-flex;
        cursor: pointer;
        user-select: none;
        overflow: hidden;
        position: relative;
        color: dt('togglebutton.color');
        background: dt('togglebutton.background');
        border: 1px solid dt('togglebutton.border.color');
        padding: dt('togglebutton.padding');
        font-size: 1rem;
        font-family: inherit;
        font-feature-settings: inherit;
        transition:
            background dt('togglebutton.transition.duration'),
            color dt('togglebutton.transition.duration'),
            border-color dt('togglebutton.transition.duration'),
            outline-color dt('togglebutton.transition.duration'),
            box-shadow dt('togglebutton.transition.duration');
        border-radius: dt('togglebutton.border.radius');
        outline-color: transparent;
        font-weight: dt('togglebutton.font.weight');
    }

    .p-togglebutton-content {
        display: inline-flex;
        flex: 1 1 auto;
        align-items: center;
        justify-content: center;
        gap: dt('togglebutton.gap');
        padding: dt('togglebutton.content.padding');
        background: transparent;
        border-radius: dt('togglebutton.content.border.radius');
        transition:
            background dt('togglebutton.transition.duration'),
            color dt('togglebutton.transition.duration'),
            border-color dt('togglebutton.transition.duration'),
            outline-color dt('togglebutton.transition.duration'),
            box-shadow dt('togglebutton.transition.duration');
    }

    .p-togglebutton:not(:disabled):not(.p-togglebutton-checked):hover {
        background: dt('togglebutton.hover.background');
        color: dt('togglebutton.hover.color');
    }

    .p-togglebutton.p-togglebutton-checked {
        background: dt('togglebutton.checked.background');
        border-color: dt('togglebutton.checked.border.color');
        color: dt('togglebutton.checked.color');
    }

    .p-togglebutton-checked .p-togglebutton-content {
        background: dt('togglebutton.content.checked.background');
        box-shadow: dt('togglebutton.content.checked.shadow');
    }

    .p-togglebutton:focus-visible {
        box-shadow: dt('togglebutton.focus.ring.shadow');
        outline: dt('togglebutton.focus.ring.width') dt('togglebutton.focus.ring.style') dt('togglebutton.focus.ring.color');
        outline-offset: dt('togglebutton.focus.ring.offset');
    }

    .p-togglebutton.p-invalid {
        border-color: dt('togglebutton.invalid.border.color');
    }

    .p-togglebutton:disabled {
        opacity: 1;
        cursor: default;
        background: dt('togglebutton.disabled.background');
        border-color: dt('togglebutton.disabled.border.color');
        color: dt('togglebutton.disabled.color');
    }

    .p-togglebutton-label,
    .p-togglebutton-icon {
        position: relative;
        transition: none;
    }

    .p-togglebutton-icon {
        color: dt('togglebutton.icon.color');
    }

    .p-togglebutton:not(:disabled):not(.p-togglebutton-checked):hover .p-togglebutton-icon {
        color: dt('togglebutton.icon.hover.color');
    }

    .p-togglebutton.p-togglebutton-checked .p-togglebutton-icon {
        color: dt('togglebutton.icon.checked.color');
    }

    .p-togglebutton:disabled .p-togglebutton-icon {
        color: dt('togglebutton.icon.disabled.color');
    }

    .p-togglebutton-sm {
        padding: dt('togglebutton.sm.padding');
        font-size: dt('togglebutton.sm.font.size');
    }

    .p-togglebutton-sm .p-togglebutton-content {
        padding: dt('togglebutton.content.sm.padding');
    }

    .p-togglebutton-lg {
        padding: dt('togglebutton.lg.padding');
        font-size: dt('togglebutton.lg.font.size');
    }

    .p-togglebutton-lg .p-togglebutton-content {
        padding: dt('togglebutton.content.lg.padding');
    }

    .p-togglebutton-fluid {
        width: 100%;
    }
`,dt={root:function(o){var l=o.instance,d=o.props;return["p-togglebutton p-component",{"p-togglebutton-checked":l.active,"p-invalid":l.$invalid,"p-togglebutton-fluid":d.fluid,"p-togglebutton-sm p-inputfield-sm":d.size==="small","p-togglebutton-lg p-inputfield-lg":d.size==="large"}]},content:"p-togglebutton-content",icon:"p-togglebutton-icon",label:"p-togglebutton-label"},ut=U.extend({name:"togglebutton",style:st,classes:dt}),pt={name:"BaseToggleButton",extends:$,props:{onIcon:String,offIcon:String,onLabel:{type:String,default:"Yes"},offLabel:{type:String,default:"No"},readonly:{type:Boolean,default:!1},tabindex:{type:Number,default:null},ariaLabelledby:{type:String,default:null},ariaLabel:{type:String,default:null},size:{type:String,default:null},fluid:{type:Boolean,default:null}},style:ut,provide:function(){return{$pcToggleButton:this,$parentInstance:this}}};function V(t){"@babel/helpers - typeof";return V=typeof Symbol=="function"&&typeof Symbol.iterator=="symbol"?function(o){return typeof o}:function(o){return o&&typeof Symbol=="function"&&o.constructor===Symbol&&o!==Symbol.prototype?"symbol":typeof o},V(t)}function gt(t,o,l){return(o=bt(o))in t?Object.defineProperty(t,o,{value:l,enumerable:!0,configurable:!0,writable:!0}):t[o]=l,t}function bt(t){var o=ct(t,"string");return V(o)=="symbol"?o:o+""}function ct(t,o){if(V(t)!="object"||!t)return t;var l=t[Symbol.toPrimitive];if(l!==void 0){var d=l.call(t,o);if(V(d)!="object")return d;throw new TypeError("@@toPrimitive must return a primitive value.")}return(o==="string"?String:Number)(t)}var C={name:"ToggleButton",extends:pt,inheritAttrs:!1,emits:["change"],methods:{getPTOptions:function(o){var l=o==="root"?this.ptmi:this.ptm;return l(o,{context:{active:this.active,disabled:this.disabled}})},onChange:function(o){!this.disabled&&!this.readonly&&(this.writeValue(!this.d_value,o),this.$emit("change",o))},onBlur:function(o){var l,d;(l=(d=this.formField).onBlur)===null||l===void 0||l.call(d,o)}},computed:{active:function(){return this.d_value===!0},hasLabel:function(){return x(this.onLabel)&&x(this.offLabel)},label:function(){return this.hasLabel?this.d_value?this.onLabel:this.offLabel:" "},dataP:function(){return D(gt({checked:this.active,invalid:this.$invalid},this.size,this.size))}},directives:{ripple:A}},mt=["tabindex","disabled","aria-pressed","aria-label","aria-labelledby","data-p-checked","data-p-disabled","data-p"],ft=["data-p"];function yt(t,o,l,d,i,s){var b=M("ripple");return j((_(),h("button",v({type:"button",class:t.cx("root"),tabindex:t.tabindex,disabled:t.disabled,"aria-pressed":t.d_value,onClick:o[0]||(o[0]=function(){return s.onChange&&s.onChange.apply(s,arguments)}),onBlur:o[1]||(o[1]=function(){return s.onBlur&&s.onBlur.apply(s,arguments)})},s.getPTOptions("root"),{"aria-label":t.ariaLabel,"aria-labelledby":t.ariaLabelledby,"data-p-checked":s.active,"data-p-disabled":t.disabled,"data-p":s.dataP}),[n("span",v({class:t.cx("content")},s.getPTOptions("content"),{"data-p":s.dataP}),[w(t.$slots,"default",{},function(){return[w(t.$slots,"icon",{value:t.d_value,class:G(t.cx("icon"))},function(){return[t.onIcon||t.offIcon?(_(),h("span",v({key:0,class:[t.cx("icon"),t.d_value?t.onIcon:t.offIcon]},s.getPTOptions("icon")),null,16)):H("",!0)]}),n("span",v({class:t.cx("label")},s.getPTOptions("label")),B(s.label),17)]})],16,ft)],16,mt)),[[b]])}C.render=yt;var vt=`
    .p-selectbutton {
        display: inline-flex;
        user-select: none;
        vertical-align: bottom;
        outline-color: transparent;
        border-radius: dt('selectbutton.border.radius');
    }

    .p-selectbutton .p-togglebutton {
        border-radius: 0;
        border-width: 1px 1px 1px 0;
    }

    .p-selectbutton .p-togglebutton:focus-visible {
        position: relative;
        z-index: 1;
    }

    .p-selectbutton .p-togglebutton:first-child {
        border-inline-start-width: 1px;
        border-start-start-radius: dt('selectbutton.border.radius');
        border-end-start-radius: dt('selectbutton.border.radius');
    }

    .p-selectbutton .p-togglebutton:last-child {
        border-start-end-radius: dt('selectbutton.border.radius');
        border-end-end-radius: dt('selectbutton.border.radius');
    }

    .p-selectbutton.p-invalid {
        outline: 1px solid dt('selectbutton.invalid.border.color');
        outline-offset: 0;
    }

    .p-selectbutton-fluid {
        width: 100%;
    }
    
    .p-selectbutton-fluid .p-togglebutton {
        flex: 1 1 0;
    }
`,_t={root:function(o){var l=o.props,d=o.instance;return["p-selectbutton p-component",{"p-invalid":d.$invalid,"p-selectbutton-fluid":l.fluid}]}},ht=U.extend({name:"selectbutton",style:vt,classes:_t}),Vt={name:"BaseSelectButton",extends:$,props:{options:Array,optionLabel:null,optionValue:null,optionDisabled:null,multiple:Boolean,allowEmpty:{type:Boolean,default:!0},dataKey:null,ariaLabelledby:{type:String,default:null},size:{type:String,default:null},fluid:{type:Boolean,default:null}},style:ht,provide:function(){return{$pcSelectButton:this,$parentInstance:this}}};function St(t,o){var l=typeof Symbol<"u"&&t[Symbol.iterator]||t["@@iterator"];if(!l){if(Array.isArray(t)||(l=R(t))||o){l&&(t=l);var d=0,i=function(){};return{s:i,n:function(){return d>=t.length?{done:!0}:{done:!1,value:t[d++]}},e:function(f){throw f},f:i}}throw new TypeError(`Invalid attempt to iterate non-iterable instance.
In order to be iterable, non-array objects must have a [Symbol.iterator]() method.`)}var s,b=!0,p=!1;return{s:function(){l=l.call(t)},n:function(){var f=l.next();return b=f.done,f},e:function(f){p=!0,s=f},f:function(){try{b||l.return==null||l.return()}finally{if(p)throw s}}}}function Tt(t){return kt(t)||Ot(t)||R(t)||Lt()}function Lt(){throw new TypeError(`Invalid attempt to spread non-iterable instance.
In order to be iterable, non-array objects must have a [Symbol.iterator]() method.`)}function R(t,o){if(t){if(typeof t=="string")return P(t,o);var l={}.toString.call(t).slice(8,-1);return l==="Object"&&t.constructor&&(l=t.constructor.name),l==="Map"||l==="Set"?Array.from(t):l==="Arguments"||/^(?:Ui|I)nt(?:8|16|32)(?:Clamped)?Array$/.test(l)?P(t,o):void 0}}function Ot(t){if(typeof Symbol<"u"&&t[Symbol.iterator]!=null||t["@@iterator"]!=null)return Array.from(t)}function kt(t){if(Array.isArray(t))return P(t)}function P(t,o){(o==null||o>t.length)&&(o=t.length);for(var l=0,d=Array(o);l<o;l++)d[l]=t[l];return d}var q={name:"SelectButton",extends:Vt,inheritAttrs:!1,emits:["change"],methods:{getOptionLabel:function(o){return this.optionLabel?T(o,this.optionLabel):o},getOptionValue:function(o){return this.optionValue?T(o,this.optionValue):o},getOptionRenderKey:function(o){return this.dataKey?T(o,this.dataKey):this.getOptionLabel(o)},isOptionDisabled:function(o){return this.optionDisabled?T(o,this.optionDisabled):!1},isOptionReadonly:function(o){if(this.allowEmpty)return!1;var l=this.isSelected(o);return this.multiple?l&&this.d_value.length===1:l},onOptionSelect:function(o,l,d){var i=this;if(!(this.disabled||this.isOptionDisabled(l)||this.isOptionReadonly(l))){var s=this.isSelected(l),b=this.getOptionValue(l),p;if(this.multiple)if(s){if(p=this.d_value.filter(function(c){return!O(c,b,i.equalityKey)}),!this.allowEmpty&&p.length===0)return}else p=this.d_value?[].concat(Tt(this.d_value),[b]):[b];else{if(s&&!this.allowEmpty)return;p=s?null:b}this.writeValue(p,o),this.$emit("change",{originalEvent:o,value:p})}},isSelected:function(o){var l=!1,d=this.getOptionValue(o);if(this.multiple){if(this.d_value){var i=St(this.d_value),s;try{for(i.s();!(s=i.n()).done;){var b=s.value;if(O(b,d,this.equalityKey)){l=!0;break}}}catch(p){i.e(p)}finally{i.f()}}}else l=O(this.d_value,d,this.equalityKey);return l}},computed:{equalityKey:function(){return this.optionValue?null:this.dataKey},dataP:function(){return D({invalid:this.$invalid})}},directives:{ripple:A},components:{ToggleButton:C}},wt=["aria-labelledby","data-p"];function Pt(t,o,l,d,i,s){var b=W("ToggleButton");return _(),h("div",v({class:t.cx("root"),role:"group","aria-labelledby":t.ariaLabelledby},t.ptmi("root"),{"data-p":s.dataP}),[(_(!0),h(Y,null,J(t.options,function(p,c){return _(),Q(b,{key:s.getOptionRenderKey(p),modelValue:s.isSelected(p),onLabel:s.getOptionLabel(p),offLabel:s.getOptionLabel(p),disabled:t.disabled||s.isOptionDisabled(p),unstyled:t.unstyled,size:t.size,readonly:s.isOptionReadonly(p),onChange:function(S){return s.onOptionSelect(S,p,c)},pt:t.ptm("pcToggleButton")},X({_:2},[t.$slots.option?{name:"default",fn:Z(function(){return[w(t.$slots,"option",{option:p,index:c},function(){return[n("span",v({ref_for:!0},t.ptm("pcToggleButton").label),B(s.getOptionLabel(p)),17)]})]}),key:"0"}:void 0]),1032,["modelValue","onLabel","offLabel","disabled","unstyled","size","readonly","onChange","pt"])}),128))],16,wt)}q.render=Pt;const Bt={class:"trip-form-wrapper"},It={class:"form-tabs"},xt={class:"form-tab active"},Ut={class:"grid grid-cols-1 lg:grid-cols-2 gap-x-12 gap-y-8"},At={class:"form-section"},Dt={class:"grid grid-cols-1 md:grid-cols-3 gap-x-4 gap-y-3"},$t={class:"form-group"},Ct={class:"form-group"},Rt={class:"form-group"},qt={class:"form-group"},Et={class:"form-group"},Nt={class:"form-group"},zt={class:"form-group"},Ft={class:"form-group"},Kt={class:"form-group"},Mt={class:"form-group col-span-1 md:col-span-2"},jt={class:"space-y-12"},Gt={class:"form-section"},Ht={class:"grid grid-cols-1 md:grid-cols-3 gap-x-4 gap-y-3"},Wt={class:"form-group"},Yt={class:"form-group"},Jt={class:"form-group"},Qt={class:"form-group"},Xt={class:"form-group"},Zt={class:"form-group"},te={class:"form-section"},ee={class:"grid grid-cols-1 md:grid-cols-3 gap-x-4 gap-y-3 items-end"},oe={class:"form-group"},ne={class:"form-group"},le={class:"form-group"},ie={class:"submit-area"},re=tt({__name:"TripForm",props:{options:{}},emits:["success","cancel"],setup(t,{emit:o}){const l=t,d=o,i=et({trip_type:"outbound",party_id:null,vendor_id:null,truck_id:null,load_site_id:null,unload_site_id:null,product_id:null,driver_id:null,payment_mode:"credit",product_units:null,product_amount:null,product_tax_id:null,empty_weight_load:null,ui_party_type:null,ui_reference_number:"",ui_requested_unit:"MT",ui_empty_time:null,ui_maistry_id:null}),s=l.options.patrons??[],b=l.options.sites??[],p=l.options.machines??[],c=l.options.products??[],f=l.options.personnels??[],S=l.options.taxes??[],I=(l.options.transports??s).map(u=>({label:u.legal_name,value:u.id})),E=[{label:"Customer",value:"customer"},{label:"Vendor",value:"vendor"},{label:"Transporter",value:"transporter"}],N=[{label:"MT",value:"MT"},{label:"Ton",value:"Ton"},{label:"Trips",value:"Trips"}],z=[{label:"Sales",value:"outbound"},{label:"Purchase",value:"inbound"}],F=nt(()=>i.trip_type==="inbound"?"TRIP - INCOMING (PURCHASE)":"TRIP - OUTGOING (SALES)"),L=u=>u===""||u===null||u===void 0?null:Number(u),K=()=>{i.transform(u=>({trip_type:u.trip_type,party_id:u.party_id,vendor_id:u.vendor_id,truck_id:u.truck_id,load_site_id:u.load_site_id,unload_site_id:u.unload_site_id,product_id:u.product_id,driver_id:u.driver_id,payment_mode:u.payment_mode,product_units:L(u.product_units),product_amount:L(u.product_amount),product_tax_id:u.product_tax_id,empty_weight_load:L(u.empty_weight_load),ui_empty_time:u.ui_empty_time?u.ui_empty_time.toISOString().slice(0,19).replace("T"," "):null})).post(route("trips.store"),{onSuccess:()=>{i.reset(),i.trip_type="outbound",i.payment_mode="credit",i.ui_requested_unit="MT",d("success")}})};return(u,e)=>(_(),h("div",Bt,[n("div",It,[n("div",xt,B(F.value),1)]),n("form",{onSubmit:ot(K,["prevent"]),class:"trip-form-main"},[n("div",Ut,[n("div",At,[e[29]||(e[29]=n("h4",{class:"section-title"},"PARTY/WEIGHTAGE",-1)),n("div",Dt,[n("div",$t,[e[19]||(e[19]=n("label",null,[y("Truck "),n("span",{class:"req"},"*")],-1)),g(m,{modelValue:r(i).truck_id,"onUpdate:modelValue":e[0]||(e[0]=a=>r(i).truck_id=a),options:r(p),optionLabel:"registration",optionValue:"id",placeholder:"Select Truck",fluid:""},null,8,["modelValue","options"])]),n("div",Ct,[e[20]||(e[20]=n("label",null,[y("Transport "),n("span",{class:"req"},"*")],-1)),g(m,{modelValue:r(i).vendor_id,"onUpdate:modelValue":e[1]||(e[1]=a=>r(i).vendor_id=a),options:r(I),optionLabel:"label",optionValue:"value",placeholder:"None",fluid:""},null,8,["modelValue","options"])]),n("div",Rt,[e[21]||(e[21]=n("label",null,[y("Party "),n("span",{class:"req"},"*")],-1)),g(m,{modelValue:r(i).party_id,"onUpdate:modelValue":e[2]||(e[2]=a=>r(i).party_id=a),options:r(s),optionLabel:"legal_name",optionValue:"id",placeholder:"Select Party",fluid:""},null,8,["modelValue","options"])]),n("div",qt,[e[22]||(e[22]=n("label",null,[y("Party Type "),n("span",{class:"req"},"*")],-1)),g(m,{modelValue:r(i).ui_party_type,"onUpdate:modelValue":e[3]||(e[3]=a=>r(i).ui_party_type=a),options:E,optionLabel:"label",optionValue:"value",placeholder:"Select Type",fluid:""},null,8,["modelValue"])]),n("div",Et,[e[23]||(e[23]=n("label",null,[y("Loading "),n("span",{class:"req"},"*")],-1)),g(m,{modelValue:r(i).load_site_id,"onUpdate:modelValue":e[4]||(e[4]=a=>r(i).load_site_id=a),options:r(b),optionLabel:"name",optionValue:"id",placeholder:"Select Site",fluid:""},null,8,["modelValue","options"])]),n("div",Nt,[e[24]||(e[24]=n("label",null,[y("Unloading Point "),n("span",{class:"req"},"*")],-1)),g(m,{modelValue:r(i).unload_site_id,"onUpdate:modelValue":e[5]||(e[5]=a=>r(i).unload_site_id=a),options:r(b),optionLabel:"name",optionValue:"id",placeholder:"Select Site",fluid:""},null,8,["modelValue","options"])]),n("div",zt,[e[25]||(e[25]=n("label",null,[y("Empty Weight "),n("span",{class:"req"},"*")],-1)),g(k,{modelValue:r(i).empty_weight_load,"onUpdate:modelValue":e[6]||(e[6]=a=>r(i).empty_weight_load=a),minFractionDigits:2,mode:"decimal",fluid:"",placeholder:"0.00"},null,8,["modelValue"])]),n("div",Ft,[e[26]||(e[26]=n("label",null,"Reference #",-1)),g(lt,{modelValue:r(i).ui_reference_number,"onUpdate:modelValue":e[7]||(e[7]=a=>r(i).ui_reference_number=a),placeholder:"400",fluid:"",class:"bg-gray-100"},null,8,["modelValue"])]),n("div",Kt,[e[27]||(e[27]=n("label",null,"Requested UNIT",-1)),g(k,{modelValue:r(i).product_units,"onUpdate:modelValue":e[8]||(e[8]=a=>r(i).product_units=a),minFractionDigits:2,mode:"decimal",fluid:"",placeholder:"0.00"},null,8,["modelValue"])]),n("div",Mt,[e[28]||(e[28]=n("label",null,"Empty Time",-1)),g(r(it),{modelValue:r(i).ui_empty_time,"onUpdate:modelValue":e[9]||(e[9]=a=>r(i).ui_empty_time=a),showTime:"",hourFormat:"24",fluid:"",showIcon:"",iconDisplay:"input"},null,8,["modelValue"])])])]),n("div",jt,[n("div",Gt,[e[36]||(e[36]=n("h4",{class:"section-title"},"PRODUCT / DRIVER DETAILS",-1)),n("div",Ht,[n("div",Wt,[e[30]||(e[30]=n("label",null,[y("Product "),n("span",{class:"req"},"*")],-1)),g(m,{modelValue:r(i).product_id,"onUpdate:modelValue":e[10]||(e[10]=a=>r(i).product_id=a),options:r(c),optionLabel:"title",optionValue:"id",placeholder:"Select",fluid:""},null,8,["modelValue","options"])]),n("div",Yt,[e[31]||(e[31]=n("label",null,"Product Rate",-1)),g(k,{modelValue:r(i).product_amount,"onUpdate:modelValue":e[11]||(e[11]=a=>r(i).product_amount=a),minFractionDigits:2,mode:"decimal",fluid:"",placeholder:"0.00"},null,8,["modelValue"])]),n("div",Jt,[e[32]||(e[32]=n("label",null,"Units",-1)),g(m,{modelValue:r(i).ui_requested_unit,"onUpdate:modelValue":e[12]||(e[12]=a=>r(i).ui_requested_unit=a),options:N,optionLabel:"label",optionValue:"value",fluid:""},null,8,["modelValue"])]),n("div",Qt,[e[33]||(e[33]=n("label",null,"Sales Tax Type",-1)),g(m,{modelValue:r(i).product_tax_id,"onUpdate:modelValue":e[13]||(e[13]=a=>r(i).product_tax_id=a),options:r(S),optionLabel:"tax_name",optionValue:"id",placeholder:"None",fluid:""},null,8,["modelValue","options"])]),n("div",Xt,[e[34]||(e[34]=n("label",null,"Driver",-1)),g(m,{modelValue:r(i).driver_id,"onUpdate:modelValue":e[14]||(e[14]=a=>r(i).driver_id=a),options:r(f),optionLabel:a=>`${a.first_name} ${a.last_name}`,optionValue:"id",placeholder:"None",fluid:""},null,8,["modelValue","options","optionLabel"])]),n("div",Zt,[e[35]||(e[35]=n("label",null,"Maistry",-1)),g(m,{modelValue:r(i).ui_maistry_id,"onUpdate:modelValue":e[15]||(e[15]=a=>r(i).ui_maistry_id=a),options:r(f),optionLabel:a=>`${a.first_name} ${a.last_name}`,optionValue:"id",placeholder:"None",fluid:""},null,8,["modelValue","options","optionLabel"])])])]),n("div",te,[e[40]||(e[40]=n("h4",{class:"section-title"},"PURCHASE DETAILS",-1)),n("div",ee,[n("div",oe,[e[37]||(e[37]=n("label",null,"Purchase/Sales",-1)),g(r(q),{modelValue:r(i).trip_type,"onUpdate:modelValue":e[16]||(e[16]=a=>r(i).trip_type=a),options:z,optionLabel:"label",optionValue:"value",class:"p-selectbutton-sm"},null,8,["modelValue"])]),n("div",ne,[e[38]||(e[38]=n("label",null,[y("Purchase Party "),n("span",{class:"req"},"*")],-1)),g(m,{modelValue:r(i).vendor_id,"onUpdate:modelValue":e[17]||(e[17]=a=>r(i).vendor_id=a),options:r(I),optionLabel:"label",optionValue:"value",fluid:""},null,8,["modelValue","options"])]),n("div",le,[e[39]||(e[39]=n("label",null,[y("Purchase Tax Type "),n("span",{class:"req"},"*")],-1)),g(m,{modelValue:r(i).product_tax_id,"onUpdate:modelValue":e[18]||(e[18]=a=>r(i).product_tax_id=a),options:r(S),optionLabel:"tax_name",optionValue:"id",fluid:""},null,8,["modelValue","options"])])])])])]),n("div",ie,[g(r(rt),{type:"submit",icon:"pi pi-arrow-right",iconPos:"right",label:r(i).processing?"ADDING...":"ADD TRIP",loading:r(i).processing,class:"p-button-outlined p-button-secondary w-full md:w-auto px-12"},null,8,["label","loading"])])],32)]))}}),Le=at(re,[["__scopeId","data-v-90a89a61"]]);export{Le as default};
