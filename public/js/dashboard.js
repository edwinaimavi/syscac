(function(){
    const selector=document.getElementById('dashboardPeriod');
    selector?.addEventListener('change',()=>document.getElementById('customDates')?.classList.toggle('is-visible',selector.value==='custom'));
    if(typeof Chart==='undefined'||!window.dashboardCharts)return;
    const data=window.dashboardCharts, money=v=>'S/ '+Number(v).toLocaleString('es-PE',{minimumFractionDigits:2,maximumFractionDigits:2});
    Chart.defaults.global.defaultFontFamily='Source Sans Pro, sans-serif';Chart.defaults.global.defaultFontSize=11;Chart.defaults.global.defaultFontColor='#74828c';
    const common={responsive:true,maintainAspectRatio:false,legend:{labels:{boxWidth:10,usePointStyle:true}},tooltips:{callbacks:{label:(item,dataset)=>`${dataset.datasets[item.datasetIndex].label}: ${money(item.yLabel)}`}},scales:{xAxes:[{gridLines:{display:false}}],yAxes:[{ticks:{beginAtZero:true},gridLines:{color:'#edf1f3'}}]}};
    function draw(id,type,labels,sets,options=common){const canvas=document.getElementById(id),values=sets.flatMap(s=>s.data.map(Number));if(!values.some(v=>v!==0)){canvas.style.display='none';canvas.nextElementSibling.style.display='flex';return}new Chart(canvas,{type,data:{labels,datasets:sets},options});}
    draw('cashChart','line',data.labels,[{label:'Ingresos',data:data.cash.income,borderColor:'#16806f',backgroundColor:'rgba(22,128,111,.08)',fill:true,lineTension:.3},{label:'Egresos',data:data.cash.expense,borderColor:'#c55454',backgroundColor:'rgba(197,84,84,.05)',fill:true,lineTension:.3}]);
    draw('profitChart','bar',data.labels,[{label:'Intereses',data:data.profit.interest,backgroundColor:'#247b86'},{label:'Moras',data:data.profit.late,backgroundColor:'#d19b39'}]);
    draw('sharesChart','bar',data.labels,[{label:'Acciones',data:data.shares,backgroundColor:'#5e6fa4'}],{...common,tooltips:{callbacks:{label:i:`Acciones: ${Number(i.yLabel).toLocaleString('es-PE')}`}}});
    const loanLabels=Object.keys(data.loans),loanValues=Object.values(data.loans);draw('loansChart','doughnut',loanLabels,[{label:'Préstamos',data:loanValues,backgroundColor:['#d2a642','#4a82a8','#247b86','#3e946a','#bd5555'],borderWidth:0}],{responsive:true,maintainAspectRatio:false,legend:{position:'right',labels:{boxWidth:10,usePointStyle:true}},tooltips:{callbacks:{label:(i,d)=>`${d.labels[i.index]}: ${d.datasets[0].data[i.index]}`}}});
})();
