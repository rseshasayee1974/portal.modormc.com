import{M as T,o as s,d,r as G,L as x,t as f,c as A,n as V,U as F,f as k,p as I,y as M,l as _,w as p,b as i,i as c,a as l,F as P,k as K,j as O,E as U}from"./app-CteCJMeo.js";import{l as q}from"./lodash-CD9vKWDB.js";import"./sweetalert2.esm.all-B_S-52a2.js";import{s as y}from"./index-zs5S_WM9.js";import{s as Q}from"./index-IeeHo4Lj.js";import{a as H,f as J}from"./index-BA_P5gVV.js";import{_ as W}from"./BaseCard.vue_vue_type_script_setup_true_lang-D5QjNVMP.js";import{B as X}from"./BaseDataTable-DLC5xING.js";import{B as C}from"./BaseActionButton-BIN3OXbt.js";import{B as Y}from"./BaseDeleteButton-BescayX2.js";import{_ as Z}from"./UserEditForm.vue_vue_type_script_setup_true_lang-D58QWBME.js";var aa=`
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
`,ea={root:function(e){var n=e.props;return["p-avatar p-component",{"p-avatar-image":n.image!=null,"p-avatar-circle":n.shape==="circle","p-avatar-lg":n.size==="large","p-avatar-xl":n.size==="xlarge"}]},label:"p-avatar-label",icon:"p-avatar-icon"},ta=T.extend({name:"avatar",style:aa,classes:ea}),ra={name:"BaseAvatar",extends:H,props:{label:{type:String,default:null},icon:{type:String,default:null},image:{type:String,default:null},size:{type:String,default:"normal"},shape:{type:String,default:"square"},ariaLabelledby:{type:String,default:null},ariaLabel:{type:String,default:null}},style:ta,provide:function(){return{$pcAvatar:this,$parentInstance:this}}};function h(a){"@babel/helpers - typeof";return h=typeof Symbol=="function"&&typeof Symbol.iterator=="symbol"?function(e){return typeof e}:function(e){return e&&typeof Symbol=="function"&&e.constructor===Symbol&&e!==Symbol.prototype?"symbol":typeof e},h(a)}function R(a,e,n){return(e=na(e))in a?Object.defineProperty(a,e,{value:n,enumerable:!0,configurable:!0,writable:!0}):a[e]=n,a}function na(a){var e=ia(a,"string");return h(e)=="symbol"?e:e+""}function ia(a,e){if(h(a)!="object"||!a)return a;var n=a[Symbol.toPrimitive];if(n!==void 0){var u=n.call(a,e);if(h(u)!="object")return u;throw new TypeError("@@toPrimitive must return a primitive value.")}return(e==="string"?String:Number)(a)}var L={name:"Avatar",extends:ra,inheritAttrs:!1,emits:["error"],methods:{onError:function(e){this.$emit("error",e)}},computed:{dataP:function(){return J(R(R({},this.shape,this.shape),this.size,this.size))}}},sa=["aria-labelledby","aria-label","data-p"],oa=["data-p"],la=["data-p"],da=["src","alt","data-p"];function pa(a,e,n,u,m,o){return s(),d("div",x({class:a.cx("root"),"aria-labelledby":a.ariaLabelledby,"aria-label":a.ariaLabel},a.ptmi("root"),{"data-p":o.dataP}),[G(a.$slots,"default",{},function(){return[a.label?(s(),d("span",x({key:0,class:a.cx("label")},a.ptm("label"),{"data-p":o.dataP}),f(a.label),17,oa)):a.$slots.icon?(s(),A(F(a.$slots.icon),{key:1,class:V(a.cx("icon"))},null,8,["class"])):a.icon?(s(),d("span",x({key:2,class:[a.cx("icon"),a.icon]},a.ptm("icon"),{"data-p":o.dataP}),null,16,la)):a.image?(s(),d("img",x({key:3,src:a.image,alt:a.ariaLabel,onError:e[0]||(e[0]=function(){return o.onError&&o.onError.apply(o,arguments)})},a.ptm("image"),{"data-p":o.dataP}),null,16,da)):k("",!0)]})],16,sa)}L.render=pa;const ca={class:"flex items-center gap-2 px-3 py-1 bg-slate-50 rounded-lg border border-slate-100"},ua={class:"text-[10px] font-black text-slate-400 uppercase tracking-widest"},va={class:"flex items-center gap-2"},ga={class:"flex flex-col"},fa={class:"font-bold"},ma={class:"text-xs text-gray-500"},ya={key:0,class:"pi pi-shield text-amber-500"},ha={key:1,class:"pi pi-circle text-gray-300"},ba={class:"flex flex-col gap-1"},xa={class:"font-bold text-blue-600 dark:text-blue-400"},_a={class:"text-gray-600 dark:text-gray-300"},wa={key:0,class:"text-gray-400"},ka={key:0,class:"text-xs text-gray-400 italic"},za={class:"flex justify-end gap-1"},ja=I({__name:"UserList",props:{users:{},filters:{},entities:{},plants:{},userGroups:{}},setup(a){const e=a;M();const n=_(e.filters.search||""),u=_(!1),m=_({}),o=_({}),D=r=>!!m.value[r],z=(r,v)=>{if(D(r.id)&&o.value[r.id]===v){w(r.id);return}m.value={[r.id]:!0},o.value[r.id]=v},w=r=>{delete m.value[r]},S=r=>{U.get(route("users.index"),r,{preserveState:!0,replace:!0,onStart:()=>u.value=!0,onFinish:()=>u.value=!1})};q.debounce(()=>S({search:n.value}),300);const N=r=>S({page:r.page+1,search:n.value}),$=r=>{w(r),U.reload({only:["users"]})};return(r,v)=>(s(),A(W,{class:"text-sm"},{default:p(()=>[i(X,{value:a.users.data,dataKey:"id",lazy:"",paginator:"",rows:a.users.per_page,totalRecords:a.users.total,loading:u.value,expandedRows:m.value,"onUpdate:expandedRows":v[0]||(v[0]=t=>m.value=t),onPage:N,showSearch:"",showSerial:"",heading:"User Directory",headingIcon:"UserGroupIcon",showExport:"",exportFilename:"user-directory-report"},{toolbar:p(()=>[l("div",ca,[l("span",ua,f(a.users.total)+" registered users",1)])]),expansion:p(t=>[i(Z,{user:t.data,mode:o.value[t.data.id]??"edit",entities:a.entities,plants:a.plants,userGroups:a.userGroups,onClose:g=>w(t.data.id),onUpdated:g=>$(t.data.id)},null,8,["user","mode","entities","plants","userGroups","onClose","onUpdated"])]),default:p(()=>[i(c(y),{header:"User"},{body:p(t=>[l("div",va,[i(c(L),{image:t.data.profile_photo_path?`/storage/${t.data.profile_photo_path}`:`https://ui-avatars.com/api/?name=${t.data.username}&background=random`,shape:"circle"},null,8,["image"]),l("div",ga,[l("span",fa,f(t.data.username),1),l("span",ma,f(t.data.email),1)])])]),_:1}),i(c(y),{field:"mobile",header:"Mobile"}),i(c(y),{field:"is_otp_enabled",header:"OTP"},{body:p(t=>[t.data.is_otp_enabled?(s(),d("i",ya)):(s(),d("i",ha))]),_:1}),i(c(y),{field:"is_active",header:"Status"},{body:p(t=>[i(c(Q),{value:t.data.is_active?"Active":"Inactive",severity:t.data.is_active?"success":"danger",rounded:""},null,8,["value","severity"])]),_:1}),i(c(y),{header:"Access & Roles"},{body:p(t=>{var g;return[l("div",ba,[(s(!0),d(P,null,K(t.data.entity_users||[],(b,j)=>{var B,E;return s(),d("div",{key:j,class:"text-[10px] bg-gray-50 dark:bg-gray-800 p-1 rounded border border-gray-100 dark:border-gray-700"},[l("span",xa,f(((B=b.role)==null?void 0:B.name)||"No Role"),1),v[1]||(v[1]=l("span",{class:"text-gray-400 mx-1"},"@",-1)),l("span",_a,[O(f(((E=b.entity)==null?void 0:E.legal_name)||"Unknown Entity")+" ",1),b.plant?(s(),d("span",wa,"("+f(b.plant.name)+")",1)):k("",!0)])])}),128)),(g=t.data.entity_users)!=null&&g.length?k("",!0):(s(),d("span",ka,"No access assigned"))])]}),_:1}),i(c(y),{header:"Actions",style:{width:"140px"}},{body:p(t=>[l("div",za,[i(C,{icon:"pi pi-eye",severity:"secondary",tooltip:"View Details",onClick:g=>z(t.data,"view")},null,8,["onClick"]),i(C,{icon:"pi pi-pencil",severity:"info",tooltip:"Edit User",onClick:g=>z(t.data,"edit")},null,8,["onClick"]),i(Y,{url:r.route("users.destroy",t.data.id),title:"Delete User?",text:"This action cannot be undone.",onSuccess:g=>$(t.data.id)},null,8,["url","onSuccess"])])]),_:1})]),_:1},8,["value","rows","totalRecords","loading","expandedRows"])]),_:1}))}});export{ja as _};
