export interface Field { key: string; label: string; type?: 'text'|'date'|'datetime-local'|'number'|'textarea'|'select'; required?: boolean; options?: [string,string][]; help?: string; }
export interface RecordDefinition { title: string; singular: string; dateField: string; fields: Field[]; }
const notes: Field = {key:'notes',label:'Notes',type:'textarea'};
export const definitions: Record<string,RecordDefinition> = {
  'timeline-entries': {title:'Timeline',singular:'entry',dateField:'entry_date',fields:[
    {key:'kind',label:'Entry type',type:'select',required:true,options:[['memory','Memory'],['milestone','Milestone'],['health_note','Health note'],['life_event','Life event']]},
    {key:'title',label:'Title',required:true},{key:'entry_date',label:'Date',type:'date',required:true},
    {key:'category',label:'Category',help:'For milestones: physical, language, cognitive, social_emotional, or personal.'},
    {key:'description',label:'Description',type:'textarea'},
  ]},
  'growth-measurements': {title:'Growth',singular:'measurement',dateField:'measured_on',fields:[
    {key:'measured_on',label:'Measurement date',type:'date',required:true},
    {key:'weight_value',label:'Weight',type:'number'},{key:'weight_unit',label:'Weight unit',type:'select',options:[['','Not recorded'],['kg','Kilograms'],['lb','Pounds']]},
    {key:'height_value',label:'Length / height',type:'number'},{key:'height_unit',label:'Length / height unit',type:'select',options:[['','Not recorded'],['cm','Centimeters'],['in','Inches']]},notes,
  ]},
  medications: {title:'Medications',singular:'medication',dateField:'created_at',fields:[
    {key:'name',label:'Medication name',required:true},{key:'instructions',label:'Recorded instructions',type:'textarea',help:'Copy instructions provided by your healthcare professional. This app does not calculate doses.'},
    {key:'instructed_amount',label:'Instructed amount',type:'number'},{key:'dose_unit',label:'Dose unit'},
    {key:'start_date',label:'Start date',type:'date'},{key:'end_date',label:'End date',type:'date'},
  ]},
  'vaccination-records': {title:'Vaccinations',singular:'vaccination',dateField:'administered_on',fields:[
    {key:'vaccine_name',label:'Vaccine name',required:true},{key:'administered_on',label:'Date administered',type:'date',required:true},
    {key:'dose_label',label:'Dose label'},{key:'provider',label:'Clinic / healthcare provider'},notes,
  ]},
  appointments: {title:'Appointments',singular:'appointment',dateField:'starts_at',fields:[
    {key:'title',label:'Appointment title',required:true},{key:'kind',label:'Visit type',type:'select',required:true,options:[['general','General visit'],['vaccination','Vaccination']]},
    {key:'starts_at',label:'Appointment date and time',type:'datetime-local',required:true},
    {key:'timezone',label:'Timezone',required:true,help:'The date and time above use this timezone.'},
    {key:'provider',label:'Doctor / healthcare provider'},{key:'clinic',label:'Clinic'},{key:'reason',label:'Reason for visit',type:'textarea'},
    {key:'status',label:'Status',type:'select',required:true,options:[['scheduled','Scheduled'],['completed','Completed'],['cancelled','Cancelled']]},
    {key:'visit_summary',label:'Visit summary',type:'textarea'},
    {key:'reminders_enabled',label:'Appointment reminders',type:'select',required:true,options:[['true','Enabled'],['false','Disabled']]},
  ]},
};
