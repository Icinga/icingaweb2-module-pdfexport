/* Icinga PDF Export | (c) 2024 Icinga GmbH | GPLv2 */

"use strict";

const FALLBACK_COLOR = 'green';

(() => {
    Layout.registerPlugin('chart-renderer', () => {
        document.querySelectorAll('.icinga-chart').forEach(element => {
            console.log('ssss');
            let attrs = element.dataset;
            let chartData = JSON.parse(attrs.chartData);

            let data = chartData.data;
            let xAxisTicks = chartData.xAxisTicks;

            let threshold = attrs.chartThreshold;
            let lineColor = attrs.chartLineColor ?? FALLBACK_COLOR;
            let belowThresholdColor = attrs.chartBelowThresholdColor ?? FALLBACK_COLOR;
            let aboveThresholdColor = attrs.chartAboveThresholdColor ?? FALLBACK_COLOR;
            let yAxisMax = Number(attrs.chartYAxisMax ?? 100);
            let yAxisMin = Number(attrs.chartYAxisMin ?? 0);

            var grid = {};
            if ('chartShowThresholdLine' in attrs) {
                grid = {
                    y: {
                        lines: [
                            {
                                value: threshold,
                                text: "threshold",
                                class: "threshold-mark",
                            },
                        ]
                    },
                };
            }

            console.log(attrs, grid);

            let chartElement = document.createElement('div');
            chartElement.classList.add('chart-element');

            element.appendChild(chartElement);

            bb.generate({
                bindto: chartElement,
                clipPath: false, // show line on 0 and 100
                zoom: {
                    enabled: true,
                    type: "drag"
                },
                data: {
                    labels: {
                        rotate: 90,
                        format: (v, id, i, texts)=> {
                            return (v === yAxisMin || v === yAxisMax) ? '' : v;
                        },
                    },
                    type: attrs.chartType,
                    x: "xAxisTicks",
                    columns: [
                        ["xAxisTicks"].concat(xAxisTicks),
                        [attrs.chartTooltipLabel].concat(data)
                    ],
                    color: (color, datapoint) => {
                        if (! ("value" in datapoint)) {
                            return lineColor;
                        }

                        return datapoint.value <= threshold ? belowThresholdColor : aboveThresholdColor;
                    },
                },
                axis: {
                    y: {
                        max: yAxisMax,
                        min: yAxisMin,
                        padding: {
                            top: 0,
                            bottom: 0
                        },
                        label: {
                            text: attrs.chartYAxisLabel,
                            position: "outer-middle",
                        },
                    },
                    x: {
                        type: attrs.chartXAxisType,
                        label: {
                            text: attrs.chartXAxisLabel,
                            position: "outer-center",
                        },
                        tick: {
                            multiline: true,
                            rotate: 60,
                            culling: {
                                max: (xAxisTicks.length / 2) < 50 ? xAxisTicks.length : (xAxisTicks.length / 2)
                            },
                            //format:_this.getFormaterFunction(attrs, xAxisTicks),
                        },
                        clipPath: false,
                        padding: {
                            right: 30,
                            unit: "px"
                        }
                    }
                },
                legend: {
                    show: 'chartShowLegend' in attrs
                },
                grid : grid,
            });
        });
    });
})();
