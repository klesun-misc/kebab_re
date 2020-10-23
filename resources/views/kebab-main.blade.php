<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <title>Kebab Main</title>
</head>
<body>
    <div style="display: flex;">
        <div style="flex: 1">
            <h2>Kebab Main</h2>
            <h3>This page is the main interface to the Kebab World</h3>
            <p>Once upon a time, a pakistanian chef made 14 very-very spicy XXL kebabs and scattered them across the world.</p>
            <p>Each spicy kebab dominated over his realm and soon 14 countries were formed. Each possessed unique balance of meat, flavoring and vegetables causing constant economy-driven military conflicts.</p>
            <p>But at one moment the most spicy and powerful of them, called Gorenie Deni, united his brethren under name of "United Kebab Republics" and then the peaceful era started.</p>
        </div>
        <div style="width: 450px;">
            <img src="{{ url('/../../') }}/public/imgs/kebab.png"/>
        </div>
    </div>
    <div>
        <p> You are a citizen of United Kebab Republics.
            Your name does not matter as well as your reasons.
            The only thing that matters is your dream.
            And your dream is to taste each and every spicy kebab made by the chef.
            To achieve that you'll have to conquer every country ruled by it's kebab.
            Good luck ^_^.
        </p>
    </div>
    <div class="briefing-holder">
        <p> Your first objective is the Mild Vegetarian Republic.
            As you could get from the name, it's not very spicy and on top of that it does not have meat in it.
            It is the weakest of all kebabs, a good starting point for you.
        </p>
    </div>
    <div class="battle-panel">
        <button class="start-battle" onclick="KebabApp.startBattle();">Start Battle!</button><label> Battle id: <span class="battle-id"></span></label>
        <br/>
        <label>Remaining spice: <span class="remaining-spice">1000</span></label> |
        <label>Stolen by you: <span class="stolen-by-you">0</span></label> |
        <label>Stolen from you: <span class="stolen-from-you">0</span></label> |
        <label title="
How much spice will you bet on your on you next move.
By betting more than your opponent, you win the round.
By winning two rounds you win the battle and take all remaining spice of your opponent as well as _spice left on the battlefield_.
The _spice left on the battlefield_ is the minimum bet amount taken from bets of both players each round.
Everything beyond this minimum goes to the player that 'lost' the round.
        ">
            Your next bet: <input type="number" min="1" class="next-bet"/>
        </label>
        <button class="bet-spice" disabled="disabled" title="Spice Battle not started yet" onclick="KebabApp.betSpice();">Bet!</button>
        <br/>
        <img src="{{ url('/../../') }}/public/imgs/vegan.png" align="right"/>
        <table class="status-board">
            <tr>
                <th colspan="2">You</th>
                <th colspan="2">Him</th>
            </tr>
            <tr>
                <th>Left</th>
                <th>Bet</th>
                <th>Bet</th>
                <th>Left</th>
            </tr>
        </table>
        <br/>
        <br/>
        <textarea class="battle-log" cols="70" rows="10" readonly="readonly">
Mild Vegetarian Guy challenges you!
Enter your bet!
        </textarea>
        <br clear="all"/>
    </div>
</body>
<script>

/** the object to use from buttons' onlick in html */
var KebabApp = function(){
    let $$ = s => [...document.querySelectorAll(s)];

    let http = function(url, params) {
        let result = {then: (resp) => {}};
        let oReq = new XMLHttpRequest();
        if (params) {
            url += '?' + Object.entries(params)
                .map(p => p.join('=')).join('&');
        }
        oReq.open("GET", url, true);
        oReq.onload = () => result.then(oReq.response);
        oReq.send(null);
        return result;
    };

    let opt = function (value, forceIsPresent = false) {
        let has = () => forceIsPresent ||
            value !== null && value !== undefined;
        return {
            map: (f) => has() ? opt(f(value)) : opt(null),
            flt: (f) => has() && f(value) ? opt(value) : opt(null),
            def: (def) => has() ? value : def,
            has: has,
            saf: (f) => { if (has()) { try { return opt(f(value)); } catch (exc) { console.error('Opt mapping threw an exception', exc); } } return opt(null); },
            set get(cb) { if (has()) { cb(value); } },
            err: (none) => {
                if (has()) {
                    return { set els(some) { some(value); } };
                } else {
                    none();
                    return { set els(some) {} };
                }
            },
        };
    };

    let mkDom = (tagName, params) => {
        let dom = document.createElement(tagName);
        for (let [k,v] of Object.entries(params || {})) {
            if (k === 'innerHTML') {
                dom.innerHTML = v;
            } else if (k === 'children') {
                v.forEach(c => dom.appendChild(c));
            } else if (k === 'style') {
                Object.keys(v).forEach(k => dom.style[k] = v[k]);
            } else {
                dom[k] = v;
                if (typeof v !== 'function') {
                    dom.setAttribute(k, v);
                }
            }
        }
        return dom;
    };

    return {
        startBattle: function() {
            let url = "{{ url('/') }}/api/start-battle";
            http(url, {}).then = (resp) => opt(resp)
                .saf(r => JSON.parse(r))
                .flt(p => !p.error)
                .err(() => alert('Server returned error ' + resp))
                .els = parsed => {
                $$('button.start-battle')[0].setAttribute('disabled', 'disabled');
                $$('button.start-battle')[0].setAttribute('title', 'Already started');
                $$('button.bet-spice')[0].setAttribute('title', 'Ready to bet!');
                $$('button.bet-spice')[0].removeAttribute('disabled');
                $$('span.battle-id')[0].innerHTML = parsed.battleId;
                $$('span.remaining-spice')[0].innerHTML = parsed.human.spice_left;
                $$('table.status-board')[0].appendChild(mkDom('tr', {children: [
                        mkDom('td', {innerHTML: parsed.human.spice_left}),
                        mkDom('td', {}),
                        mkDom('td', {}),
                        mkDom('td', {innerHTML: parsed.ai.spice_left}),
                    ]}));
            };
        },
        betSpice: function () {
            let url = "{{ url('/') }}/api/bet-spice";
            let params = {
                battleId: $$('span.battle-id')[0].innerHTML,
                betAmount: $$('input.next-bet')[0].value,
            };
            http(url, params).then = (resp) => opt(resp)
                .saf(r => JSON.parse(r))
                .flt(p => !p.error)
                .err(() => alert('Server returned error ' + resp))
                .els = parsed => {
                    let humanStars = '*'.repeat(parsed.human.rounds_won);
                    let aiStars = '*'.repeat(parsed.ai.rounds_won);
                    $$('span.remaining-spice')[0].innerHTML = parsed.human.spice_left;
                    $$('span.stolen-by-you')[0].innerHTML = parsed.human.spice_stolen;
                    $$('span.stolen-from-you')[0].innerHTML = parsed.ai.spice_stolen;
                    $$('table.status-board')[0].appendChild(mkDom('tr', {children: [
                        mkDom('td', {innerHTML: parsed.human.spice_left + humanStars}),
                        mkDom('td', {innerHTML: parsed.humanBet}),
                        mkDom('td', {innerHTML: parsed.aiBet}),
                        mkDom('td', {innerHTML: parsed.ai.spice_left + aiStars}),
                    ]}));
            };
        },
    };
}();

</script>
<style>
    h1,h2,h3 {
        text-align: center;
    }
    img {
        width: 300px;
    }
    body {
        padding-left: 2%;
    }
    p {
        margin-top: 0px;
        margin-bottom: 0px;
    }
    .briefing-holder {
        margin-top: 20px;
    }
    .battle-panel {
        margin: 2px;
        padding: 2px;
        border: solid saddlebrown 2px;
    }
    table {
        border-collapse:collapse;
        border:1px solid black;
    }
    td, th {
        padding: 2px;
        padding-right: 4px;
        padding-left: 4px;
        border:1px solid black;
    }
</style>
</html>
