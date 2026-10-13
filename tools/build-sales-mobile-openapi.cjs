/*
Author: ragul-onemodo
Created: 2026-10-12 13:28:21 Asia/Calcutta (UTC+05:30)
*/
const fs=require('node:fs');
const path=require('node:path');
const collection=require('../docs/postman/sales-executive-mobile.postman_collection.json');
const names={lead_id:'lead',activity_id:'activity',quotation_id:'quotation',customer_id:'customer',order_id:'order',schedule_id:'schedule'};
const values={email:'executive@example.com',reviewer_email:'reviewer@example.com',password:'your-password',reviewer_password:'your-password',mobile:'+919876543210',reviewer_mobile:'+919876543211',challenge_id:'0'.repeat(64),otp:'123456',today:'2026-10-12',validity_date:'2026-10-19'};
const required={
 'auth/login':['email','password'],'auth/send-otp':['mobile'],'auth/verify-otp':['challenge_id','otp'],
 leads:['contact_name','enquiry_date','source','priority','status','expected_value'],
 'follow-ups':['lead_id','type','subject'],quotations:['patron_id','site_id','quote_date','is_tax_inclusive','items'],
 orders:['customer_id','site_id','mix_design_id','customer_po_id','total_qty'],
 'batch-schedules':['schedule_date','sales_order_id','site_id','mix_design_id','qty_m3','order_volume_m3','pump_type']
};
function resolve(value){
 if(Array.isArray(value))return value.map(resolve);
 if(value && typeof value==='object')return Object.fromEntries(Object.entries(value).map(([k,v])=>[k,resolve(v)]));
 if(typeof value!=='string')return value;
 const token=value.match(/^\{\{([^}]+)\}\}$/);
 if(token)return values[token[1]]??1;
 const resolved=value.replace(/\{\{([^}]+)\}\}/g,(_,key)=>values[key]??'1');
 return /^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/.test(resolved)?resolved.replace(' ','T')+'+05:30':resolved;
}
function schema(value,key=''){
 if(Array.isArray(value))return {type:'array',items:schema(value[0]??{}),minItems:1};
 if(value && typeof value==='object')return {type:'object',properties:Object.fromEntries(Object.entries(value).map(([k,v])=>[k,schema(v,k)]))};
 if(value===null)return {type:key.endsWith('_id')?'integer':key==='email'?'string':'string',nullable:true};
 if(typeof value==='boolean')return {type:'boolean'};
 if(typeof value==='number')return {type:key.endsWith('_id')?'integer':'number',...(key.endsWith('_id')?{minimum:1}:{})};
 const result={type:'string'};
 if(/email/.test(key))result.format='email';
 if(key==='password')result.writeOnly=true;
 if(key==='challenge_id')Object.assign(result,{minLength:64,maxLength:64});
 if(key==='otp')Object.assign(result,{minLength:6,maxLength:6,pattern:'^[0-9]{6}$',writeOnly:true});
 if(key.endsWith('_date')||key==='enquiry_date')result.format='date';
 return result;
}
const error=(status)=>({'$ref':'#/components/responses/'+status});
const json=(example,bodySchema={type:'object'})=>({'application/json':{schema:bodySchema,...(example?{example}:{})}});
const commonError={type:'object',properties:{message:{type:'string'},error_code:{type:'string',description:'WhatsApp failures: WHATSAPP_TLS_ERROR, WHATSAPP_CONNECTION_ERROR or WHATSAPP_SUBMISSION_FAILED.'},errors:{type:'object',additionalProperties:{oneOf:[{type:'string'},{type:'array',items:{type:'string'}}]}}}};
const spec={openapi:'3.0.3',info:{title:'MODO Sales Executive Mobile API',version:'1.0.0',description:'Test the ERP mobile APIs here. First grant the user Mobile App Access in Membership > Users. Login using email/password, or send WhatsApp OTP then verify it. Copy data.token into Authorize (without Bearer), and supply X-Plant-Id for workspace requests. The OTP template is sales_executive_login with one six-digit parameter. Codes expire in five minutes. Swagger does not send requests until you click Execute. Existing module permissions and plant ownership still apply.'},servers:[{url:'/api/mobile/v1',description:'Current ERP server'}],security:[{bearerAuth:[]}],tags:[],paths:{},components:{securitySchemes:{bearerAuth:{type:'http',scheme:'bearer',description:'Paste the device token from data.token after login or OTP verification.'}},responses:{}}};
for(const [name,description] of Object.entries({Unauthorized:'Invalid or expired bearer token.',Forbidden:'Mobile access not granted, account inactive/locked, inaccessible plant or missing permission.',NotFound:'Record unavailable in the user scope.',ValidationError:'Input validation or lifecycle restriction. Display message and field errors.',TooManyRequests:'Request or OTP attempt limit reached.',ServiceUnavailable:'WhatsApp provider not configured, rejected the template or could not confirm submission.',Conflict:'OTP verification already in progress. Try again shortly.'}))spec.components.responses[name]={description,content:json(null,commonError)};
for(const folder of collection.item)for(const item of folder.item){
 const request=item.request;
 const route=request.url.path.slice(3).join('/');
 const endpoint='/'+route.replace(/\{\{([^}]+)\}\}/g,(_,name)=>'{'+(names[name]??name)+'}');
 const method=request.method.toLowerCase();
 if(spec.paths[endpoint]?.[method])continue;
 const group=route.split('/')[0];
 const tag=group==='auth'||['me','plants'].includes(group)?'Authentication':group==='follow-ups'?'Follow ups':group==='batch-schedules'?'Batch schedules':group==='orders'?'Sales orders':group==='marketing'||group==='dashboard'||group==='lookups'?'Workspace':group[0].toUpperCase()+group.slice(1);
 if(!spec.tags.some(t=>t.name===tag))spec.tags.push({name:tag});
 const parameters=[];
 for(const match of endpoint.matchAll(/\{([^}]+)\}/g))parameters.push({name:match[1],in:'path',required:true,schema:{type:'integer',minimum:1}});
 for(const p of request.url.query??[])parameters.push({name:p.key,in:'query',required:false,schema:p.key==='per_page'?{type:'integer',minimum:1,maximum:100,default:20}:p.key==='schedule_date'?{type:'string',format:'date'}:p.key==='view'?{type:'string',enum:['today','upcoming','overdue','completed','all']}:{type:'string'}});
 const isPublic=request.auth?.type==='noauth';
 if(!isPublic && !['me','plants','auth/logout'].includes(route))parameters.push({name:'X-Plant-Id',in:'header',required:true,description:'Accessible plant ID returned by login or GET /plants.',schema:{type:'integer',minimum:1}});
 let successCode=method==='post' && ['leads','quotations','orders','follow-ups'].includes(route)?'201':'200';
 if(route==='auth/send-otp')successCode='202';
 const successExample=route==='auth/send-otp'?{success:true,data:{otp_required:true,channel:'whatsapp',challenge_id:'0'.repeat(64),expires_in:300}}:route==='auth/login'||route==='auth/verify-otp'?{success:true,data:{token:'paste-this-token-into-Authorize',token_type:'Bearer',expires_at:'2026-11-11T13:30:00+05:30',user:{id:1,name:'Sales Executive',email:'executive@example.com',personnel_id:1},plants:[{id:1,name:'Example Plant',entity_id:1}]}}:null;
 const op={tags:[tag],summary:item.name,operationId:method+'_'+endpoint.replace(/[^a-zA-Z0-9]+/g,'_'),description:request.description,security:isPublic?[]:[{bearerAuth:[]}],parameters,responses:{[successCode]:{description:route==='auth/send-otp'?'WhatsApp submission accepted; no token until verification.':'Success. Decimal fields can serialize as strings. Lists use Laravel pagination under data. Schedule creation/update reuse the existing schedule response field.',content:json(successExample)},'401':error('Unauthorized'),'403':error('Forbidden'),'404':error('NotFound'),'422':error('ValidationError'),'429':error('TooManyRequests')}};
 if(route==='auth/send-otp')op.responses['503']=error('ServiceUnavailable');
 if(route==='auth/verify-otp')op.responses['409']=error('Conflict');
 if(request.body?.raw){
   const example=resolve(JSON.parse(request.body.raw));const bodySchema=schema(example);
   bodySchema.required=required[route]??required[group]??[];
   if(route.endsWith('/review')){bodySchema.required=['decision'];bodySchema.properties.decision.enum=['approved','rejected'];bodySchema.properties.comment.description='Required when rejecting.';}
   if(group==='follow-ups' && !route.endsWith('/complete'))bodySchema.properties.due_at.description='Required unless type is Note. Use an ISO timestamp or ERP local datetime.';
   if(group==='leads' && bodySchema.properties.phone){bodySchema.properties.phone.description='Phone or email is required.';bodySchema.properties.status.enum=['New','Contacted','Qualified','Unqualified'];}
   if(route.endsWith('/convert'))delete bodySchema.required;
   if(group==='quotations' && example.items){bodySchema.properties.items.maxItems=100;bodySchema.properties.items.items.required=['mix_design_id','quantity','rate'];}
   if(group==='batch-schedules'){bodySchema.properties.status.enum=['scheduled'];bodySchema.properties.sales_order_id.description='Accessible confirmed/in-progress order with matching site and mix design.';}
   op.requestBody={required:true,content:{'application/json':{schema:bodySchema,example}}};
 }
 if(route.endsWith('/complete')){if(op.requestBody)delete op.requestBody.content['application/json'].schema.required;}
 spec.paths[endpoint]??={};spec.paths[endpoint][method]=op;
 if(method==='put' && ['leads','quotations','orders'].includes(group)){spec.paths[endpoint].patch={...op,operationId:op.operationId.replace(/^put_/,'patch_'),summary:op.summary+' (PATCH)',description:op.description+' PATCH accepts the same full form as PUT.'};}
}
const destination=path.join(__dirname,'../docs/openapi');fs.mkdirSync(destination,{recursive:true});fs.writeFileSync(path.join(destination,'sales-executive-mobile.openapi.json'),JSON.stringify(spec,null,2)+'\n');console.log('Generated '+Object.values(spec.paths).reduce((n,p)=>n+Object.keys(p).length,0)+' OpenAPI operations.');
