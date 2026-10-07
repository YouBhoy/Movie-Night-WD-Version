const fs = require('node:fs');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const path = require('node:path');
const engine = require('../assets/seat-selection.js');
let checks = 0;
function check(actual, expected) { assert.deepEqual(actual,expected); checks++; }
const seat = (row,position,extra={}) => ({id:row+position,seat_number:row+position,row_letter:row,seat_position:position,status:'available',...extra});
const seats = ['A','B'].flatMap(row=>Array.from({length:6},(_,i)=>seat(row,i+1)));
check(engine.adjacent([seat('A',1),seat('A',2)]),true);
check(engine.adjacent([seat('A',1),seat('B',1)]),false);
check(engine.adjacent([seat('A',1,{block_id:'left'}),seat('A',2,{block_id:'right'})]),false);
check(engine.issues(seats,[seat('A',2)],3).length,0);
check(engine.issues(seats,[seat('A',1),seat('A',3)],3).length,0);
check(engine.recommend(seats,[seat('A',1),seat('A',3)],3).map(s=>s.seat_number),['A1','A2','A3']);
check(engine.issues(seats,[seat('A',1),seat('A',3)],2).some(p=>p.key==='gap:A2'),true);
check(engine.issues(seats,[seat('A',1),seat('A',4)],3).some(p=>p.type==='split'),true);
check(engine.issues(seats,[seat('A',1),seat('B',1)],3).some(p=>p.type==='split'),true);
check(engine.recommend(seats,[seat('A',2)],3).map(s=>s.seat_number),['A1','A2','A3']);
const blocked=seats.map(s=>s.seat_number==='A2'? {...s,status:'occupied'}:s);
check(engine.blocks(blocked,3).some(block=>block.some(s=>s.seat_number==='A2')),false);
check(engine.blocks([seat('A',1),seat('A',3)],2).length,0);
check(engine.recommend([seat('A',1)],[],2).length,0);
const source=fs.readFileSync(path.join(__dirname,'../index.php'),'utf8');
const code=source.slice(source.indexOf('        function refreshSelection()'),source.indexOf('        function clearSeatSelections()'));
const elements={};
const context=vm.createContext({SeatSelection:engine,selectedSeats:[],allSeats:seats,pendingGapSeat:null,pendingWarning:null,pendingNonAdjacentSelection:false,selectionBeforeWarning:[],acceptedSeatWarnings:new Set(),recommendedSeats:[],attendeeCountSelect:{value:'3'},document:{getElementById:id=>elements[id] ||= {style:{},textContent:'',hidden:false},querySelectorAll:()=>[]},updateSeatDisplay(){},updateSelectedSeatsDisplay(){context.updateSeatRecommendation();},updateSubmitButtonState(){},showError(message){throw new Error(message);}});
vm.runInContext(code,context);
context.handleSeatClick(seat('A',2));
check(context.pendingWarning,null);
context.useSeatRecommendation();
check(Array.from(context.selectedSeats,s=>s.seat_number),['A1','A2','A3']);
check(context.pendingWarning,null);
context.selectedSeats=[];context.acceptedSeatWarnings.clear();
context.handleSeatClick(seat('A',1));context.handleSeatClick(seat('B',1));
check(context.pendingWarning.type,'split');
context.confirmNonAdjacentSelection();
check(context.pendingWarning,null);
context.handleSeatClick(seat('B',2));
check(context.pendingWarning,null);
context.selectedSeats=[];context.acceptedSeatWarnings.clear();
context.handleSeatClick(seat('A',1));context.handleSeatClick(seat('B',1));
context.cancelNonAdjacentSelection();
check(Array.from(context.selectedSeats,s=>s.seat_number),['A1']);
context.selectedSeats=[];context.attendeeCountSelect.value='2';
context.handleSeatClick(seat('A',1));context.handleSeatClick(seat('A',3));
check(context.pendingWarning.type,'gap');
context.confirmGapSelection();
check(context.pendingWarning.type,'split');
context.confirmNonAdjacentSelection();
check(context.pendingWarning,null);
check(context.acceptedSeatWarnings.has('gap:A2'),true);
async function availabilityChecks() {
    const submission = source.slice(source.indexOf('        async function handleFormSubmission('), source.indexOf('        function validateForm('));
    const requests = [];
    let errorMessage = '';
    Object.assign(context, {
        currentHallId:1,currentShiftId:1,csrfToken:'test-token',URLSearchParams,
        validateForm:()=>true,showLoading(){},
        showError(message){errorMessage=message;},
        buildSeatMap(){},renderSeatMap(){context.selectedSeats=[];},
        fetch:async (url,options)=>{requests.push(options);return {ok:true,json:async()=>({success:true,seats:blocked})};}
    });
    context.selectedSeats=[seat('A',1),seat('A',2)];
    context.pendingWarning=null;
    context.pendingNonAdjacentSelection=false;
    vm.runInContext(submission,context);
    await context.handleFormSubmission({preventDefault(){}});
    check(Array.from(context.selectedSeats,s=>s.seat_number),['A1']);
    check(requests.length,1);
    check(errorMessage.includes('were taken'),true);
    check(context.recommendedSeats.every(s=>s.seat_number!=='A2'),true);
    console.log('PASS: '+checks+' group-selection checks');
}
availabilityChecks().catch(error=>{console.error(error);process.exitCode=1;});
