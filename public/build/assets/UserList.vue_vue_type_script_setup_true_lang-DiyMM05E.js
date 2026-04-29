import{B as P,o as p,d as c,r as F,A as x,t as g,c as L,n as I,J as M,h as $,k as K,s as O,i as _,w as u,b as n,e as d,a as s,F as q,g as J,f as Q,M as S}from"./app-B1Xz-Nfc.js";import{l as Y}from"./lodash-IrjTj4Y1.js";import{S as H}from"./sweetalert2.esm.all-Cj9UHshf.js";import{s as m}from"./index-CrRzexAu.js";import{s as W}from"./index-D2QRkIE_.js";import{a as X,f as Z}from"./index-vVErtHBV.js";import{_ as aa}from"./BaseCard.vue_vue_type_script_setup_true_lang-DbcSdYO0.js";import{_ as z}from"./BaseButton.vue_vue_type_script_setup_true_lang-CL30NqI9.js";import{B as ea}from"./BaseInput-BZQomMyb.js";import{B as ta}from"./BaseDataTable-CWDai6cm.js";import{_ as ra}from"./UserEditForm.vue_vue_type_script_setup_true_lang-CkKLoIgt.js";var na=`
    .p-avatar {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: dt('avatar.width');
        height: dt('avatar.height');
        font-size: dt('avatar.font.size');
        background: dt('avatar.background');
        color: dt('avatar.color');
        border-radius: dt('avatar.border.radius');
    }

    .p-avatar-image {
        background: transparent;
    }

    .p-avatar-circle {
        border-radius: 50%;
    }

    .p-avatar-circle img {
        border-radius: 50%;
    }

    .p-avatar-icon {
        font-size: dt('avatar.icon.size');
        width: dt('avatar.icon.size');
        height: dt('avatar.icon.size');
    }

    .p-avatar img {
        width: 100%;
        height: 100%;
    }

    .p-avatar-lg {
        width: dt('avatar.lg.width');
        height: dt('avatar.lg.width');
        font-size: dt('avatar.lg.font.size');
    }

    .p-avatar-lg .p-avatar-icon {
        font-size: dt('avatar.lg.icon.size');
        width: dt('avatar.lg.icon.size');
        height: dt('avatar.lg.icon.size');
    }

    .p-avatar-xl {
        width: dt('avatar.xl.width');
        height: dt('avatar.xl.width');
        font-size: dt('avatar.xl.font.size');
    }

    .p-avatar-xl .p-avatar-icon {
        font-size: dt('avatar.xl.icon.size');
        width: dt('avatar.xl.icon.size');
        height: dt('avatar.xl.icon.size');
    }

    .p-avatar-group {
        display: flex;
        align-items: center;
    }

    .p-avatar-group .p-avatar + .p-avatar {
        margin-inline-start: dt('avatar.group.offset');
    }

    .p-avatar-group .p-avatar {
        border: 2px solid dt('avatar.group.border.color');
    }

    .p-avatar-group .p-avatar-lg + .p-avatar-lg {
        margin-inline-start: dt('avatar.lg.group.offset');
    }

    .p-avatar-group .p-avatar-xl + .p-avatar-xl {
        margin-inline-start: dt('avatar.xl.group.offset');
    }
`,sa={root:function(t){var i=t.props;return["p-avatar p-component",{"p-avatar-image":i.image!=null,"p-avatar-circle":i.shape==="circle","p-avatar-lg":i.size==="large","p-avatar-xl":i.size==="xlarge"}]},label:"p-avatar-label",icon:"p-avatar-icon"},ia=P.extend({name:"avatar",style:na,classes:sa}),oa={name:"BaseAvatar",extends:X,props:{label:{type:String,default:null},icon:{type:String,default:null},image:{type:String,default:null},size:{type:String,default:"normal"},shape:{type:String,default:"square"},ariaLabelledby:{type:String,default:null},ariaLabel:{type:String,default:null}},style:ia,provide:function(){return{$pcAvatar:this,$parentInstance:this}}};function y(a){"@babel/helpers - typeof";return y=typeof Symbol=="function"&&typeof Symbol.iterator=="symbol"?function(t){return typeof t}:function(t){return t&&typeof Symbol=="function"&&t.constructor===Symbol&&t!==Symbol.prototype?"symbol":typeof t},y(a)}function A(a,t,i){return(t=la(t))in a?Object.defineProperty(a,t,{value:i,enumerable:!0,configurable:!0,writable:!0}):a[t]=i,a}function la(a){var t=da(a,"string");return y(t)=="symbol"?t:t+""}function da(a,t){if(y(a)!="object"||!a)return a;var i=a[Symbol.toPrimitive];if(i!==void 0){var v=i.call(a,t);if(y(v)!="object")return v;throw new TypeError("@@toPrimitive must return a primitive value.")}return(t==="string"?String:Number)(a)}var N={name:"Avatar",extends:oa,inheritAttrs:!1,emits:["error"],methods:{onError:function(t){this.$emit("error",t)}},computed:{dataP:function(){return Z(A(A({},this.shape,this.shape),this.size,this.size))}}},pa=["aria-labelledby","aria-label","data-p"],ua=["data-p"],ca=["data-p"],va=["src","alt","data-p"];function ma(a,t,i,v,h,o){return p(),c("div",x({class:a.cx("root"),"aria-labelledby":a.ariaLabelledby,"aria-label":a.ariaLabel},a.ptmi("root"),{"data-p":o.dataP}),[F(a.$slots,"default",{},function(){return[a.label?(p(),c("span",x({key:0,class:a.cx("label")},a.ptm("label"),{"data-p":o.dataP}),g(a.label),17,ua)):a.$slots.icon?(p(),L(M(a.$slots.icon),{key:1,class:I(a.cx("icon"))},null,8,["class"])):a.icon?(p(),c("span",x({key:2,class:[a.cx("icon"),a.icon]},a.ptm("icon"),{"data-p":o.dataP}),null,16,ca)):a.image?(p(),c("img",x({key:3,src:a.image,alt:a.ariaLabel,onError:t[0]||(t[0]=function(){return o.onError&&o.onError.apply(o,arguments)})},a.ptm("image"),{"data-p":o.dataP}),null,16,va)):$("",!0)]})],16,pa)}N.render=ma;const fa={class:"flex items-center justify-between gap-4"},ga={class:"w-full max-w-sm"},ya={class:"text-gray-400 font-bold"},ha={class:"flex items-center gap-2"},ba={class:"flex flex-col"},xa={class:"font-bold"},_a={class:"text-xs text-gray-500"},wa={key:0,class:"pi pi-shield text-amber-500"},ka={key:1,class:"pi pi-circle text-gray-300"},Sa={class:"flex flex-col gap-1"},za={class:"font-bold text-blue-600 dark:text-blue-400"},$a={class:"text-gray-600 dark:text-gray-300"},Ba={key:0,class:"text-gray-400"},Ca={key:0,class:"text-xs text-gray-400 italic"},Ua={class:"flex justify-end gap-1"},Fa=K({__name:"UserList",props:{users:{},filters:{},entities:{},plants:{},userGroups:{}},setup(a){const t=a,i=O(),v=_(t.filters.search||""),h=_(!1),o=_({}),w=_({}),V=r=>!!o.value[r],B=(r,l)=>{if(V(r.id)&&w.value[r.id]===l){k(r.id);return}o.value={[r.id]:!0},w.value[r.id]=l},k=r=>{delete o.value[r]},T=r=>{H.fire({title:"Delete User?",text:"This action cannot be undone.",icon:"warning",showCancelButton:!0,confirmButtonColor:"#fe0000",confirmButtonText:"Yes, delete it!"}).then(l=>{l.isConfirmed&&S.delete(route("users.destroy",r),{onSuccess:()=>i.add({severity:"success",summary:"Deleted",detail:"User removed"})})})},C=r=>{S.get(route("users.index"),r,{preserveState:!0,replace:!0,onStart:()=>h.value=!0,onFinish:()=>h.value=!1})},U=Y.debounce(()=>C({search:v.value}),300),j=r=>C({page:r.page+1,search:v.value}),D=r=>{k(r),S.reload({only:["users"]})};return(r,l)=>(p(),L(aa,{class:"text-sm"},{header:u(()=>[s("div",fa,[l[2]||(l[2]=s("div",{class:"flex items-center gap-2"},[s("i",{class:"pi pi-user-plus text-indigo-500"}),s("span",{class:"text-md font-semibold uppercase text-gray-800 dark:text-gray-100"}," List of Users ")],-1)),s("div",ga,[n(ea,{modelValue:v.value,"onUpdate:modelValue":[l[0]||(l[0]=e=>v.value=e),d(U)],placeholder:"Search Users...",onBlur:d(U)},null,8,["modelValue","onBlur","onUpdate:modelValue"])])])]),default:u(()=>[n(ta,{value:a.users.data,dataKey:"id",lazy:"",paginator:"",rows:a.users.per_page,totalRecords:a.users.total,loading:h.value,expandedRows:o.value,"onUpdate:expandedRows":l[1]||(l[1]=e=>o.value=e),onPage:j},{expansion:u(e=>[n(ra,{user:e.data,mode:w.value[e.data.id]??"edit",entities:a.entities,plants:a.plants,userGroups:a.userGroups,onClose:f=>k(e.data.id),onUpdated:f=>D(e.data.id)},null,8,["user","mode","entities","plants","userGroups","onClose","onUpdated"])]),default:u(()=>[n(d(m),{expander:"",style:{width:"3rem"}}),n(d(m),{header:"S.No",style:{width:"60px"}},{body:u(e=>[s("span",ya,g(e.index+1),1)]),_:1}),n(d(m),{header:"User"},{body:u(e=>[s("div",ha,[n(d(N),{image:e.data.profile_photo_path?`/storage/${e.data.profile_photo_path}`:`https://ui-avatars.com/api/?name=${e.data.username}&background=random`,shape:"circle"},null,8,["image"]),s("div",ba,[s("span",xa,g(e.data.username),1),s("span",_a,g(e.data.email),1)])])]),_:1}),n(d(m),{field:"mobile",header:"Mobile"}),n(d(m),{field:"is_otp_enabled",header:"OTP"},{body:u(e=>[e.data.is_otp_enabled?(p(),c("i",wa)):(p(),c("i",ka))]),_:1}),n(d(m),{field:"is_active",header:"Status"},{body:u(e=>[n(d(W),{value:e.data.is_active?"Active":"Inactive",severity:e.data.is_active?"success":"danger",rounded:""},null,8,["value","severity"])]),_:1}),n(d(m),{header:"Access & Roles"},{body:u(e=>{var f;return[s("div",Sa,[(p(!0),c(q,null,J(e.data.entity_users||[],(b,G)=>{var E,R;return p(),c("div",{key:G,class:"text-[10px] bg-gray-50 dark:bg-gray-800 p-1 rounded border border-gray-100 dark:border-gray-700"},[s("span",za,g(((E=b.role)==null?void 0:E.name)||"No Role"),1),l[3]||(l[3]=s("span",{class:"text-gray-400 mx-1"},"@",-1)),s("span",$a,[Q(g(((R=b.entity)==null?void 0:R.legal_name)||"Unknown Entity")+" ",1),b.plant?(p(),c("span",Ba,"("+g(b.plant.name)+")",1)):$("",!0)])])}),128)),(f=e.data.entity_users)!=null&&f.length?$("",!0):(p(),c("span",Ca,"No access assigned"))])]}),_:1}),n(d(m),{header:"Actions",style:{width:"130px"}},{body:u(e=>[s("div",Ua,[n(z,{icon:"pi pi-eye",text:"",rounded:"",severity:"secondary",onClick:f=>B(e.data,"view")},null,8,["onClick"]),n(z,{icon:"pi pi-pencil",text:"",rounded:"",severity:"info",onClick:f=>B(e.data,"edit")},null,8,["onClick"]),n(z,{icon:"pi pi-trash",text:"",rounded:"",severity:"danger",onClick:f=>T(e.data.id)},null,8,["onClick"])])]),_:1})]),_:1},8,["value","rows","totalRecords","loading","expandedRows"])]),_:1}))}});export{Fa as _};
