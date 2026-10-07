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
    // Index by physical position once per operation; never cache mutable availability.
    function positionKey(seat, offset = 0) {
        return JSON.stringify([seat.row_letter, String(seat.block_id ?? ''), Number(seat.seat_position) + offset]);
    }
    function layoutIndex(all) {
        const index = new Map();
        for (const seat of all) {
            const key = positionKey(seat);
            if (!index.has(key)) index.set(key, seat);
        }
        return index;
    }
    function gaps(all, selected) {
        return findGaps(all, selected, layoutIndex(all));
    }
    function findGaps(all, selected, index) {
        const chosen = new Set(selected.map(s => s.seat_number));
        return all.filter(seat => {
            if (seat.status !== 'available' || chosen.has(seat.seat_number)) return false;
            const before = index.get(positionKey(seat, -1));
            const after = index.get(positionKey(seat, 1));
            const taken = s => !s || s.status !== 'available' || chosen.has(s.seat_number);
            return ((before && chosen.has(before.seat_number)) || (after && chosen.has(after.seat_number))) && taken(before) && taken(after);
        });
    }
    function blocks(all, count) {
        if (!Number.isInteger(count) || count < 1) return [];
        const available = all.filter(s => s.status === 'available');
        const results = [];
        const index = layoutIndex(available);
        for (const start of available) {
            const block = [];
            for (let offset=0; offset<count; offset++) {
                const seat = index.get(positionKey(start, offset));
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
        const index = layoutIndex(all);
        const score = block => {
            const retained = selected.filter(s => block.some(b => b.seat_number === s.seat_number)).length;
            const distance = anchor ? Math.abs(Number(block[0].seat_position)-Number(anchor.seat_position)) + (sameBlock(anchor,block[0]) ? 0 : 100) : 0;
            return (selected.length-retained)*10000 + findGaps(all,block,index).length*1000 + distance;
        };
        const ranked = candidates.map(block => ({block, score: score(block)}));
        ranked.sort((a,b) => a.score - b.score || a.block[0].seat_number.localeCompare(b.block[0].seat_number));
        return ranked[0]?.block || [];
    }
    function issues(all, selected, count) {
        if (!selected.length) return [];
        const index = layoutIndex(all);
        const completions = blocks(all,count).filter(block => selected.every(s => block.some(b => b.seat_number === s.seat_number)));
        // A partial selection is sensible if it can still become a complete group without stranded seats.
        if (selected.length < count && completions.some(block => !findGaps(all,block,index).length)) return [];
        const result = findGaps(all,selected,index).map(seat => ({key:'gap:'+seat.seat_number, type:'gap', message:'Seat '+seat.seat_number+' would be left alone between your group and a row boundary or unavailable seats.'}));
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
