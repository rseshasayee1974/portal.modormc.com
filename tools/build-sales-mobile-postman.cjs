/*
Author: ragul-onemodo
Created: 2026-10-09 18:10:59 Asia/Calcutta (UTC+05:30)
*/
const fs = require('node:fs');
const path = require('node:path');
const folder = path.join(__dirname, '..', 'docs', 'postman');
fs.mkdirSync(folder, {recursive: true});
const items = [];
const authBody = {email:'{{email}}',password:'{{password}}',device_name:'Postman Android'};
const lead = {contact_name:'Arun Kumar',company_name:'Kavin Builders',phone:'9876543210',email:null,enquiry_date:'{{today}}',source:'Referral',status:'New',priority:'High',requirement:'M25 Design',estimated_quantity:40,expected_value:200000,remarks:'Site enquiry'};
const follow = {lead_id:'{{lead_id}}',type:'Site Visit',subject:'Review concrete requirement',description:'Confirm site access',due_at:'{{today}} 15:00:00'};
const quote = {patron_id:'{{customer_id}}',site_id:'{{site_id}}',quote_date:'{{today}}',validity_date:'{{validity_date}}',is_tax_inclusive:true,notes:'Delivery subject to schedule confirmation',items:[{mix_design_id:'{{mix_design_id}}',quantity:3.55,rate:546,tax_id:'{{sales_tax_id}}'}]};
const order = {customer_id:'{{customer_id}}',site_id:'{{site_id}}',mix_design_id:'{{mix_design_id}}',customer_po_id:'{{customer_po_id}}',total_qty:40,scheduled_start:null,scheduled_end:null};
const schedule = {schedule_date:'{{today}}',sales_order_id:'{{order_id}}',site_id:'{{site_id}}',mix_design_id:'{{mix_design_id}}',qty_m3:6,order_volume_m3:40,pump_type:'direct_pour',vehicle_id:null,driver_id:null,pump_vehicle_id:null,batching_time:null,dispatch_time:null,eta_site:null,unloading_start:null,unloading_end:null,status:'scheduled',notes:'Requested by sales executive'};
function add(group, name, method, route, body, options={}) {
    let f=items.find(x=>x.name===group); if(!f){f={name:group,item:[]};items.push(f);}
    const scripts=["pm.test('Successful response', () => pm.expect(pm.response.code).to.be.within(200, 299));"];
    if(options.login) scripts.push("const d=pm.response.json().data; if(d.token) pm.environment.set('"+(options.manager?'reviewer_token':'api_token')+"',d.token); if(d.challenge_id) { pm.environment.set('challenge_id',d.challenge_id); pm.environment.unset('"+(options.manager?'reviewer_token':'api_token')+"'); pm.environment.unset('otp'); }");
    if(options.id) scripts.push("const j=pm.response.json(); const d=j.data || j.schedule; if(d && d.id) pm.environment.set('"+options.id+"',d.id);");
    const request={method,header:[{key:'Accept',value:'application/json'},{key:'X-Plant-Id',value:'{{plant_id}}'}],url:{raw:'{{base_url}}/api/mobile/v1/'+route,host:['{{base_url}}'],path:['api','mobile','v1',...route.split('?')[0].split('/')]},description:options.description || 'See docs/sales-executive-mobile-api.md for permissions and field rules.'};
    if(route.includes('?'))request.url.query=route.split('?')[1].split('&').map(v=>{const [key,value]=v.split('=');return {key,value};});
    if(body){request.header.push({key:'Content-Type',value:'application/json'});request.body={mode:'raw',raw:JSON.stringify(body,null,2),options:{raw:{language:'json'}}};}
    if(options.public) request.auth={type:'noauth'};
    if(options.manager && !options.public)request.auth={type:'bearer',bearer:[{key:'token',value:'{{reviewer_token}}',type:'string'}]};
    f.item.push({name,request,event:[{listen:'test',script:{type:'text/javascript',exec:scripts}}]});
}
add('Authentication','Login','POST','auth/login',authBody,{public:true,login:true,description:'Email/password login returns the bearer token directly. Alternative: Send WhatsApp OTP and Verify OTP.'});
add('Authentication','Send WhatsApp OTP','POST','auth/send-otp',{mobile:'{{mobile}}',device_name:'Postman - Sales Executive'},{public:true,login:true,description:'Passwordless alternative. Set mobile to a uniquely registered user number. Returns 202 after WhatsApp provider submission. Enter the received code in otp and run Verify OTP.'});
add('Authentication','Verify OTP','POST','auth/verify-otp',{challenge_id:'{{challenge_id}}',otp:'{{otp}}'},{public:true,login:true});
add('Authentication','Profile','GET','me'); add('Authentication','Accessible plants','GET','plants');
add('Authentication','Reviewer login','POST','auth/login',{...authBody,email:'{{reviewer_email}}',password:'{{reviewer_password}}'},{public:true,login:true,manager:true,description:'Email/password login returns reviewer_token directly.'});
add('Authentication','Reviewer send WhatsApp OTP','POST','auth/send-otp',{mobile:'{{reviewer_mobile}}',device_name:'Postman - Sales Manager'},{public:true,login:true,manager:true,description:'Passwordless alternative. Enter reviewer WhatsApp code in otp, then run Reviewer verify OTP.'});
add('Authentication','Reviewer verify OTP','POST','auth/verify-otp',{challenge_id:'{{challenge_id}}',otp:'{{otp}}'},{public:true,login:true,manager:true});
add('Workspace','Dashboard','GET','dashboard');add('Workspace','Dropdown lookups','GET','lookups');add('Workspace','Marketing summary','GET','marketing/summary');
add('Customers','List customers','GET','customers?per_page=20');add('Customers','Customer and progress','GET','customers/{{customer_id}}');
add('Leads','List leads','GET','leads?per_page=20');add('Leads','Create lead','POST','leads',lead,{id:'lead_id'});add('Leads','Lead detail','GET','leads/{{lead_id}}');
add('Leads','Update lead','PUT','leads/{{lead_id}}',{...lead,status:'Qualified'});add('Leads','Convert qualified lead','POST','leads/{{lead_id}}/convert',{customer_id:null});
add('Follow ups','List follow ups','GET','follow-ups?view=today&per_page=20');add('Follow ups','Create follow up','POST','follow-ups',follow,{id:'activity_id'});add('Follow ups','Update follow up','PUT','follow-ups/{{activity_id}}',follow);add('Follow ups','Complete follow up','POST','follow-ups/{{activity_id}}/complete',{description:'Site requirement confirmed'});
add('Quotations','List quotations','GET','quotations?per_page=20');add('Quotations','Create quotation','POST','quotations',quote,{id:'quotation_id'});add('Quotations','Quotation detail','GET','quotations/{{quotation_id}}');add('Quotations','Edit quotation','PUT','quotations/{{quotation_id}}',quote);add('Quotations','Submit for approval','POST','quotations/{{quotation_id}}/submit');
add('Quotations','Approve quotation (different reviewer)','POST','quotations/{{quotation_id}}/review',{decision:'approved',comment:'Rates approved'},{manager:true});add('Quotations','Reject quotation (alternative to approve)','POST','quotations/{{quotation_id}}/review',{decision:'rejected',comment:'Revise rate before resubmission'},{manager:true});
add('Sales orders','List orders','GET','orders?per_page=20');add('Sales orders','Create order against customer PO','POST','orders',order,{id:'order_id'});add('Sales orders','Order progress','GET','orders/{{order_id}}');add('Sales orders','Edit unstarted order','PUT','orders/{{order_id}}',order);add('Sales orders','Order batches','GET','orders/{{order_id}}/batches?per_page=20');add('Sales orders','Order dispatches','GET','orders/{{order_id}}/dispatches?per_page=20');
add('Batch schedules','List schedules','GET','batch-schedules?schedule_date={{today}}&per_page=20');add('Batch schedules','Create planned schedule','POST','batch-schedules',schedule,{id:'schedule_id'});add('Batch schedules','Schedule detail','GET','batch-schedules/{{schedule_id}}');add('Batch schedules','Edit unlinked planned schedule','PUT','batch-schedules/{{schedule_id}}',schedule);
for(const [label,route] of [['Lead','leads/{{lead_id}}'],['Follow up','follow-ups/{{activity_id}}'],['Draft quotation','quotations/{{quotation_id}}'],['Unstarted order','orders/{{order_id}}'],['Unlinked schedule','batch-schedules/{{schedule_id}}']])add('Delete actions (run individually)', 'Delete '+label,'DELETE',route,null,{description:'Soft delete. Run manually against a disposable record. Lifecycle restrictions apply.'});
add('Authentication','Logout this device (run last)','POST','auth/logout');
const collection={info:{name:'MODO Sales Executive Mobile API v1',description:'ERP APIs for the mobile prototype. Set an ERP base_url, not the Sites preview URL. Run delete actions individually. Approval and rejection are alternative actions, and require a different reviewer. Author: ragul-onemodo.',schema:'https://schema.getpostman.com/json/collection/v2.1.0/collection.json'},auth:{type:'bearer',bearer:[{key:'token',value:'{{api_token}}',type:'string'}]},event:[{listen:'prerequest',script:{type:'text/javascript',exec:["if(!pm.environment.get('today'))pm.environment.set('today',new Date().toISOString().slice(0,10));","if(!pm.environment.get('validity_date'))pm.environment.set('validity_date',new Date(Date.now()+7*86400000).toISOString().slice(0,10));"]}}],item:items};
const defaults={base_url:'http://localhost',email:'',password:'',mobile:'',plant_id:'',api_token:'',reviewer_email:'',reviewer_password:'',reviewer_mobile:'',reviewer_token:'',challenge_id:'',otp:'',today:'',validity_date:'',lead_id:'',activity_id:'',quotation_id:'',customer_id:'',customer_po_id:'',site_id:'',mix_design_id:'',sales_tax_id:'',order_id:'',schedule_id:''};
const environment={name:'MODO Sales Mobile - Local',values:Object.entries(defaults).map(([key,value])=>({key,value,enabled:true,type:/password|token|otp|challenge/.test(key)?'secret':'default'})),_postman_variable_scope:'environment'};
fs.writeFileSync(path.join(folder,'sales-executive-mobile.postman_collection.json'),JSON.stringify(collection,null,2)+'\n');
fs.writeFileSync(path.join(folder,'sales-executive-mobile.postman_environment.json'),JSON.stringify(environment,null,2)+'\n');
console.log('Generated '+items.reduce((n,f)=>n+f.item.length,0)+' Postman requests; environment contains no credentials.');
