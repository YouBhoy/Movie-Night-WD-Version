(function (root) {
    'use strict';
    function sameBlock(a, b) {
        return a.row_letter === b.row_letter && String(a.block_id ?? '') === String(b.block_id ?? '');
    }
    function adjacent(seats) {
        if (seats.length < 2) return true;
        const sorted = seats.slice().sort((a,b) => Number(a.seat_position) - Number(b.seat_position));
        return sorted.every((seat,i) => sameBlock(seat,sorted[0]) && (!i || Number(seat.seat_position) === Number(sorted[i-1].seat_position) + 1));
    }
    function gaps(all, selected) {
        const chosen = new Set(selected.map(s => s.seat_number));
        return all.filter(seat => {
            if (seat.status !== 'available' || chosen.has(seat.seat_number)) return false;
            const before = all.find(s => sameBlock(s,seat) && Number(s.seat_position) === Number(seat.seat_position)-1);
            const after = all.find(s => sameBlock(s,seat) && Number(s.seat_position) === Number(seat.seat_position)+1);
            const taken = s => !s || s.status !== 'available' || chosen.has(s.seat_number);
            return ((before && chosen.has(before.seat_number)) || (after && chosen.has(after.seat_number))) && taken(before) && taken(after);
        });
    }
    function blocks(all, count) {
        if (!Number.isInteger(count) || count < 1) return [];
        const available = all.filter(s => s.status === 'available');
        const results = [];
        for (const start of available) {
            const block = [];
            for (let offset=0; offset<count; offset++) {
                const seat = available.find(s => sameBlock(s,start) && Number(s.seat_position) === Number(start.seat_position)+offset);
                if (!seat) break;
                block.push(seat);
            }
            if (block.length === count) results.push(block);
        }
        return results;
    }
    function recommend(all, selected, count) {
        const candidates = blocks(all,count);
        const anchor = selected[selected.length-1];
        const score = block => {
            const retained = selected.filter(s => block.some(b => b.seat_number === s.seat_number)).length;
            const distance = anchor ? Math.abs(Number(block[0].seat_position)-Number(anchor.seat_position)) + (sameBlock(anchor,block[0]) ? 0 : 100) : 0;
            return (selected.length-retained)*10000 + gaps(all,block).length*1000 + distance;
        };
        candidates.sort((a,b) => score(a)-score(b) || a[0].seat_number.localeCompare(b[0].seat_number));
        return candidates[0] || [];
    }
    function issues(all, selected, count) {
        if (!selected.length) return [];
        const completions = blocks(all,count).filter(block => selected.every(s => block.some(b => b.seat_number === s.seat_number)));
        // A partial selection is sensible if it can still become a complete group without stranded seats.
        if (selected.length < count && completions.some(block => !gaps(all,block).length)) return [];
        const result = gaps(all,selected).map(seat => ({key:'gap:'+seat.seat_number, type:'gap', message:'Seat '+seat.seat_number+' would be left alone between your group and a row boundary or unavailable seats.'}));
        if (!adjacent(selected) && !completions.length) {
            const rows = [...new Set(selected.map(s => s.row_letter))].sort();
            const blockNames = [...new Set(selected.map(s => s.row_letter+':'+(s.block_id ?? '')))].sort();
            const sorted = selected.slice().sort((a,b)=>Number(a.seat_position)-Number(b.seat_position));
            const breaks = sorted.slice(1).filter((seat,i)=>sameBlock(seat,sorted[i]) && Number(seat.seat_position)>Number(sorted[i].seat_position)+1).map((seat)=>seat.row_letter+':'+seat.seat_position);
            result.push({key:'split:'+blockNames.join(',')+':'+breaks.join(','),type:'split',message: rows.length>1 ? 'Your seats are in different rows ('+rows.join(', ')+').' : 'Your seats are separated by a gap or aisle in row '+rows[0]+'.'});
        }
        return result;
    }
    const api = {sameBlock, adjacent, gaps, blocks, recommend, issues};
    if (typeof module !== 'undefined' && module.exports) module.exports = api;
    else root.SeatSelection = api;
})(typeof globalThis !== 'undefined' ? globalThis : this);
