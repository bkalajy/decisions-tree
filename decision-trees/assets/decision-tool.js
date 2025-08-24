(function(){
    // Simple, safe evaluator: supports groups (all/any/not) + ops on fields.
    function evalCond(cond, values){
      if (!cond || typeof cond !== 'object') return false;
  
      if (cond.all) return cond.all.every(c => evalCond(c, values));
      if (cond.any) return cond.any.some(c => evalCond(c, values));
      if (cond.not) return !evalCond(cond.not, values);
  
      if (cond.in && cond.in.field && Array.isArray(cond.in.values)) {
        return cond.in.values.includes(values[cond.in.field]);
      }
  
      // Binary comparison
      var field = cond.field, op = cond.op, val = cond.value;
      if (typeof field === 'undefined' || typeof op === 'undefined') return false;
  
      var v = values[field];
      // convert numbers if both look numeric
      var numOps = ['>','>=','<','<='];
      if (numOps.includes(op)) {
        v = Number(v); val = Number(val);
        if (Number.isNaN(v) || Number.isNaN(val)) return false;
      }
  
      switch(op){
        case '==': return v == val;
        case '!=': return v != val;
        case '>': return v > val;
        case '>=': return v >= val;
        case '<': return v < val;
        case '<=': return v <= val;
        default: return false;
      }
    }
  
    function firstMatch(rules, values){
      for (var i=0;i<rules.length;i++){
        try {
          if (evalCond(rules[i].if, values)) return rules[i].then;
        } catch(e) { /* ignore bad rule */ }
      }
      return null;
    }
  
    function render(instance){
      var root = document.getElementById(instance.containerId);
      if (!root) return;
  
      var cfg = instance.config || {};
      var fields = Array.isArray(cfg.fields) ? cfg.fields : [];
      var rules  = Array.isArray(cfg.rules)  ? cfg.rules  : [];
  
      // Container
      var h2 = document.createElement('h2');
      h2.className = 'dtree-title';
      h2.textContent = cfg.title || 'Decision Tool';
      root.appendChild(h2);
  
      var form = document.createElement('form');
      form.className = 'dtree-form';
      form.setAttribute('novalidate','novalidate');
      form.addEventListener('submit', function(e){ e.preventDefault(); });
      root.appendChild(form);
  
      var inputs = {};
  
      fields.forEach(function(f){
        var wrap = document.createElement('div'); wrap.className = 'dtree-field';
        var label = document.createElement('label'); label.className = 'dtree-label';
        label.textContent = f.label || f.id;
        label.setAttribute('for', instance.containerId + '-' + f.id);
        wrap.appendChild(label);
  
        var el;
        if (f.type === 'number') {
          el = document.createElement('input');
          el.type = 'number';
          if (typeof f.min !== 'undefined') el.min = String(f.min);
          if (typeof f.max !== 'undefined') el.max = String(f.max);
          if (typeof f.step !== 'undefined') el.step = String(f.step);
          el.placeholder = f.placeholder || '';
        } else if (f.type === 'select' && Array.isArray(f.options)) {
          el = document.createElement('select');
          var opt0 = document.createElement('option'); opt0.value = ''; opt0.textContent = 'Choose…';
          el.appendChild(opt0);
          f.options.forEach(function(o){
            var opt = document.createElement('option');
            opt.value = String(o.value);
            opt.textContent = o.label;
            el.appendChild(opt);
          });
        } else {
          el = document.createElement('input'); el.type='text';
        }
        el.id = instance.containerId + '-' + f.id;
        el.className = 'dtree-input';
        el.required = !!f.required;
  
        wrap.appendChild(el);
        form.appendChild(wrap);
        inputs[f.id] = el;
      });
  
      var btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'dtree-button';
      btn.textContent = cfg.cta || 'Get recommendation';
      form.appendChild(btn);
  
      var resultBox = document.createElement('div');
      resultBox.className = 'dtree-result';
      resultBox.style.display = 'none';
  
      var badge = document.createElement('div');
      badge.className = 'dtree-badge';
      resultBox.appendChild(badge);
  
      var text = document.createElement('div');
      text.className = 'dtree-expl';
      resultBox.appendChild(text);
  
      root.appendChild(resultBox);
  
      function collectValues(){
        var out = {};
        fields.forEach(function(f){
          var val = inputs[f.id].value;
          if (f.type === 'number' && val !== '') out[f.id] = Number(val);
          else out[f.id] = val;
        });
        return out;
      }
  
      function show(statusKey, message){
        resultBox.style.display = 'block';
        text.textContent = message || '';
  
        badge.textContent = (cfg.statusLabels && cfg.statusLabels[statusKey]) ||
                            (statusKey==='ok'?'Recommended':statusKey==='no'?'Not recommended':'Recommended with restriction');
  
        badge.classList.remove('dtree-ok','dtree-no','dtree-restrict');
        if (statusKey === 'ok') badge.classList.add('dtree-ok');
        else if (statusKey === 'no') badge.classList.add('dtree-no');
        else badge.classList.add('dtree-restrict');
      }
  
      btn.addEventListener('click', function(){
        // simple required check
        for (var i=0;i<fields.length;i++){
          if (fields[i].required && !inputs[fields[i].id].value) {
            show('no','Please complete all fields.');
            return;
          }
        }
        var values = collectValues();
        var match = firstMatch(rules, values);
        if (!match) { show('no','Criteria not met.'); return; }
        var status = (match.status==='ok' || match.status==='restrict') ? match.status : 'no';
        show(status, String(match.message || ''));
      });
    }
  
    document.addEventListener('DOMContentLoaded', function(){
      if (!window.DTREE_INSTANCES) return;
      window.DTREE_INSTANCES.forEach(render);
    });
  })();