(() => {
  const section=document.querySelector('[data-seller-slider]');
  if(!section)return;
  const track=section.querySelector('.dist-sellers-track');
  const pause=section.querySelector('[data-sellers-pause]');
  const motion=matchMedia('(prefers-reduced-motion: reduce)');
  let paused=motion.matches, hovered=false, touching=false, inView=false;
  const update=()=>{
    const label=paused?'Play slider':'Pause slider';
    pause.setAttribute('aria-label',label);pause.title=label;
    pause.textContent=paused?'\u25b6':'\u275a\u275a';
  };
  const move=direction=>{
    const step=track.children[1].offsetLeft-track.children[0].offsetLeft;
    const end=track.scrollWidth-track.clientWidth;
    const target=direction>0 ? (track.scrollLeft>=end-2?0:Math.min(end,track.scrollLeft+step)) : (track.scrollLeft<=2?end:Math.max(0,track.scrollLeft-step));
    track.scrollTo({left:target,behavior:motion.matches?'instant':'smooth'});
  };
  section.querySelector('[data-sellers-prev]').addEventListener('click',()=>move(-1));
  section.querySelector('[data-sellers-next]').addEventListener('click',()=>move(1));
  pause.addEventListener('click',()=>{paused=!paused;update();});
  section.addEventListener('mouseenter',()=>hovered=true);
  section.addEventListener('mouseleave',()=>hovered=false);
  track.addEventListener('touchstart',()=>touching=true,{passive:true});
  track.addEventListener('touchend',()=>touching=false,{passive:true});
  track.addEventListener('touchcancel',()=>touching=false,{passive:true});
  motion.addEventListener('change',()=>{paused=motion.matches;update();});
  new IntersectionObserver(entries=>{inView=entries[0].isIntersecting;}).observe(section);
  setInterval(()=>{
    if(!paused&&!hovered&&!touching&&inView&&!document.hidden&&!section.contains(document.activeElement))move(1);
  },3500);
  update();
})();
