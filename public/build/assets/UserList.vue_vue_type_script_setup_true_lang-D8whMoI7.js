import{M as T,o,d,r as V,L as x,t as y,c as R,n as G,U as F,f as k,p as M,y as P,l as w,w as u,b as i,i as c,a as s,F as I,k as K,j as O,E as U}from"./app-Bbi1Q1_2.js";import{l as q}from"./lodash--FmfJKLL.js";import"./sweetalert2.esm.all-Cj9UHshf.js";import{s as m}from"./index-B9fkz33_.js";import{s as Q}from"./index-_CQkPwPO.js";import{a as H,f as J}from"./index-CVX1OX7t.js";import{_ as W}from"./BaseCard.vue_vue_type_script_setup_true_lang-CUS2EQzk.js";import{B as X}from"./BaseDataTable-CqNQRhuh.js";import{B as C}from"./BaseActionButton-CO14QTSj.js";import{B as Y}from"./BaseDeleteButton-D1FhgJpA.js";import{_ as Z}from"./UserEditForm.vue_vue_type_script_setup_true_lang-DTENVPTY.js";var aa=`
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
`,ea={root:function(e){var n=e.props;return["p-avatar p-component",{"p-avatar-image":n.image!=null,"p-avatar-circle":n.shape==="circle","p-avatar-lg":n.size==="large","p-avatar-xl":n.size==="xlarge"}]},label:"p-avatar-label",icon:"p-avatar-icon"},ta=T.extend({name:"avatar",style:aa,classes:ea}),ra={name:"BaseAvatar",extends:H,props:{label:{type:String,default:null},icon:{type:String,default:null},image:{type:String,default:null},size:{type:String,default:"normal"},shape:{type:String,default:"square"},ariaLabelledby:{type:String,default:null},ariaLabel:{type:String,default:null}},style:ta,provide:function(){return{$pcAvatar:this,$parentInstance:this}}};function h(a){"@babel/helpers - typeof";return h=typeof Symbol=="function"&&typeof Symbol.iterator=="symbol"?function(e){return typeof e}:function(e){return e&&typeof Symbol=="function"&&e.constructor===Symbol&&e!==Symbol.prototype?"symbol":typeof e},h(a)}function L(a,e,n){return(e=na(e))in a?Object.defineProperty(a,e,{value:n,enumerable:!0,configurable:!0,writable:!0}):a[e]=n,a}function na(a){var e=ia(a,"string");return h(e)=="symbol"?e:e+""}function ia(a,e){if(h(a)!="object"||!a)return a;var n=a[Symbol.toPrimitive];if(n!==void 0){var v=n.call(a,e);if(h(v)!="object")return v;throw new TypeError("@@toPrimitive must return a primitive value.")}return(e==="string"?String:Number)(a)}var A={name:"Avatar",extends:ra,inheritAttrs:!1,emits:["error"],methods:{onError:function(e){this.$emit("error",e)}},computed:{dataP:function(){return J(L(L({},this.shape,this.shape),this.size,this.size))}}},sa=["aria-labelledby","aria-label","data-p"],oa=["data-p"],la=["data-p"],da=["src","alt","data-p"];function pa(a,e,n,v,f,l){return o(),d("div",x({class:a.cx("root"),"aria-labelledby":a.ariaLabelledby,"aria-label":a.ariaLabel},a.ptmi("root"),{"data-p":l.dataP}),[V(a.$slots,"default",{},function(){return[a.label?(o(),d("span",x({key:0,class:a.cx("label")},a.ptm("label"),{"data-p":l.dataP}),y(a.label),17,oa)):a.$slots.icon?(o(),R(F(a.$slots.icon),{key:1,class:G(a.cx("icon"))},null,8,["class"])):a.icon?(o(),d("span",x({key:2,class:[a.cx("icon"),a.icon]},a.ptm("icon"),{"data-p":l.dataP}),null,16,la)):a.image?(o(),d("img",x({key:3,src:a.image,alt:a.ariaLabel,onError:e[0]||(e[0]=function(){return l.onError&&l.onError.apply(l,arguments)})},a.ptm("image"),{"data-p":l.dataP}),null,16,da)):k("",!0)]})],16,sa)}A.render=pa;const ua={class:"flex items-center gap-2"},ca={class:"flex flex-col"},va={class:"font-bold"},ga={class:"text-xs text-gray-500"},fa={key:0,class:"pi pi-shield text-amber-500"},ma={key:1,class:"pi pi-circle text-gray-300"},ya={class:"flex flex-col gap-1"},ha={class:"font-bold text-blue-600 dark:text-blue-400"},ba={class:"text-gray-600 dark:text-gray-300"},xa={key:0,class:"text-gray-400"},wa={key:0,class:"text-xs text-gray-400 italic"},_a={class:"flex justify-end gap-1"},ja=M({__name:"UserList",props:{users:{},filters:{},entities:{},plants:{},userGroups:{}},setup(a){const e=a;P();const n=w(e.filters.search||""),v=w(!1),f=w({}),l=w({}),j=r=>!!f.value[r],z=(r,p)=>{if(j(r.id)&&l.value[r.id]===p){_(r.id);return}f.value={[r.id]:!0},l.value[r.id]=p},_=r=>{delete f.value[r]},S=r=>{U.get(route("users.index"),r,{preserveState:!0,replace:!0,onStart:()=>v.value=!0,onFinish:()=>v.value=!1})};q.debounce(()=>S({search:n.value}),300);const N=r=>S({page:r.page+1,search:n.value}),$=r=>{_(r),U.reload({only:["users"]})};return(r,p)=>(o(),R(W,{class:"text-sm"},{header:u(()=>[...p[1]||(p[1]=[s("div",{class:"flex items-center justify-between gap-4"},[s("div",{class:"flex items-center gap-2"},[s("i",{class:"pi pi-user-plus text-indigo-500"}),s("span",{class:"text-md font-semibold uppercase text-gray-800 dark:text-gray-100"}," List of Users ")])],-1)])]),default:u(()=>[i(X,{value:a.users.data,dataKey:"id",lazy:"",paginator:"",rows:a.users.per_page,totalRecords:a.users.total,loading:v.value,expandedRows:f.value,"onUpdate:expandedRows":p[0]||(p[0]=t=>f.value=t),onPage:N,showSearch:"",showSerial:""},{expansion:u(t=>[i(Z,{user:t.data,mode:l.value[t.data.id]??"edit",entities:a.entities,plants:a.plants,userGroups:a.userGroups,onClose:g=>_(t.data.id),onUpdated:g=>$(t.data.id)},null,8,["user","mode","entities","plants","userGroups","onClose","onUpdated"])]),default:u(()=>[i(c(m),{header:"User"},{body:u(t=>[s("div",ua,[i(c(A),{image:t.data.profile_photo_path?`/storage/${t.data.profile_photo_path}`:`https://ui-avatars.com/api/?name=${t.data.username}&background=random`,shape:"circle"},null,8,["image"]),s("div",ca,[s("span",va,y(t.data.username),1),s("span",ga,y(t.data.email),1)])])]),_:1}),i(c(m),{field:"mobile",header:"Mobile"}),i(c(m),{field:"is_otp_enabled",header:"OTP"},{body:u(t=>[t.data.is_otp_enabled?(o(),d("i",fa)):(o(),d("i",ma))]),_:1}),i(c(m),{field:"is_active",header:"Status"},{body:u(t=>[i(c(Q),{value:t.data.is_active?"Active":"Inactive",severity:t.data.is_active?"success":"danger",rounded:""},null,8,["value","severity"])]),_:1}),i(c(m),{header:"Access & Roles"},{body:u(t=>{var g;return[s("div",ya,[(o(!0),d(I,null,K(t.data.entity_users||[],(b,D)=>{var B,E;return o(),d("div",{key:D,class:"text-[10px] bg-gray-50 dark:bg-gray-800 p-1 rounded border border-gray-100 dark:border-gray-700"},[s("span",ha,y(((B=b.role)==null?void 0:B.name)||"No Role"),1),p[2]||(p[2]=s("span",{class:"text-gray-400 mx-1"},"@",-1)),s("span",ba,[O(y(((E=b.entity)==null?void 0:E.legal_name)||"Unknown Entity")+" ",1),b.plant?(o(),d("span",xa,"("+y(b.plant.name)+")",1)):k("",!0)])])}),128)),(g=t.data.entity_users)!=null&&g.length?k("",!0):(o(),d("span",wa,"No access assigned"))])]}),_:1}),i(c(m),{header:"Actions",style:{width:"140px"}},{body:u(t=>[s("div",_a,[i(C,{icon:"pi pi-eye",severity:"secondary",tooltip:"View Details",onClick:g=>z(t.data,"view")},null,8,["onClick"]),i(C,{icon:"pi pi-pencil",severity:"info",tooltip:"Edit User",onClick:g=>z(t.data,"edit")},null,8,["onClick"]),i(Y,{url:r.route("users.destroy",t.data.id),title:"Delete User?",text:"This action cannot be undone.",onSuccess:g=>$(t.data.id)},null,8,["url","onSuccess"])])]),_:1})]),_:1},8,["value","rows","totalRecords","loading","expandedRows"])]),_:1}))}});export{ja as _};
