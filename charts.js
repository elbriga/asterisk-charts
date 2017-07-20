function init() {
	desenha('2017-07-19', 1);
	desenha('2017-07-18', 2);
	desenha('2017-07-17', 3);
}

function desenha(d, g) {
	var dia = d==undefined ? $('#cxTxtDia').val() : d;
	var gfx = g==undefined ? $('#cxTxtGfx').val() : g;
	
	$.getJSON( "data/simults-"+dia+".json", function( data ) {
		$('#gfx'+gfx).html('');
		var data2 = Array(), idx;
		for(idx in data) {
			// Deixar so das 07:00 as 21:00
			if(idx >=7 && idx <= 21) data2[data2.length] = data[idx];
		}
		
		Morris.Line({
			element: 'gfx' + gfx,
			data:     data2,
			ymax:     100,
			xkey:    'data',
			ykeys:      ['minc',   'maxc',   'mins',    'maxs'    ],
			labels:     ['MINchs', 'MAXchs', 'MINsess', 'MAXsess' ],
			lineColors: [ '#AAC',  '#AAE',   '#844',    '#D22'    ]
		});
	});
}
